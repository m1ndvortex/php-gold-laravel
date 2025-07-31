<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    /**
     * Get chart of accounts
     */
    public function chartOfAccounts(Request $request): JsonResponse
    {
        $accounts = Account::with('parent', 'children')
            ->when($request->type, fn($q) => $q->byType($request->type))
            ->when($request->active_only, fn($q) => $q->active())
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->localized_name,
                    'type' => $account->type,
                    'subtype' => $account->subtype,
                    'parent_id' => $account->parent_id,
                    'level' => $account->level,
                    'is_active' => $account->is_active,
                    'is_system' => $account->is_system,
                    'current_balance' => $account->current_balance,
                    'normal_balance' => $account->normal_balance,
                    'full_path' => $account->full_path,
                    'has_children' => $account->hasChildren(),
                ];
            }),
        ]);
    }

    /**
     * Create a new account
     */
    public function createAccount(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'subtype' => 'nullable|string',
            'parent_id' => 'nullable|exists:accounts,id',
            'opening_balance' => 'nullable|numeric',
            'normal_balance' => 'required|in:debit,credit',
        ]);

        try {
            $account = $this->accountingService->createAccount([
                'code' => Account::generateCode($request->type, $request->parent_code),
                'name' => $request->name,
                'name_en' => $request->name_en,
                'description' => $request->description,
                'description_en' => $request->description_en,
                'type' => $request->type,
                'subtype' => $request->subtype,
                'parent_id' => $request->parent_id,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'normal_balance' => $request->normal_balance,
                'is_active' => true,
                'is_system' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'حساب با موفقیت ایجاد شد',
                'data' => $account,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد حساب: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an account
     */
    public function updateAccount(Request $request, Account $account): JsonResponse
    {
        if ($account->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'حساب‌های سیستمی قابل ویرایش نیستند',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        try {
            $account->update($request->only([
                'name', 'name_en', 'description', 'description_en', 'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'حساب با موفقیت به‌روزرسانی شد',
                'data' => $account,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در به‌روزرسانی حساب: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get journal entries
     */
    public function journalEntries(Request $request): JsonResponse
    {
        $query = JournalEntry::with(['details.account', 'creator'])
            ->when($request->status, fn($q) => $q->byStatus($request->status))
            ->when($request->start_date && $request->end_date, fn($q) => 
                $q->inDateRange($request->start_date, $request->end_date))
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        $entries = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Create a journal entry
     */
    public function createJournalEntry(Request $request): JsonResponse
    {
        $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'reference' => 'nullable|string',
            'is_recurring' => 'boolean',
            'recurring_pattern' => 'nullable|in:monthly,quarterly,yearly',
            'next_recurring_date' => 'nullable|date|after:entry_date',
            'details' => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:accounts,id',
            'details.*.description' => 'nullable|string',
            'details.*.debit_amount' => 'nullable|numeric|min:0',
            'details.*.credit_amount' => 'nullable|numeric|min:0',
            'details.*.cost_center' => 'nullable|string',
        ]);

        // Validate that each detail has either debit or credit, but not both
        foreach ($request->details as $index => $detail) {
            $debit = $detail['debit_amount'] ?? 0;
            $credit = $detail['credit_amount'] ?? 0;

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                return response()->json([
                    'success' => false,
                    'message' => "Row " . ($index + 1) . ": Each row must have either debit or credit amount, not both",
                ], 422);
            }
        }

        // Validate that total debits equal total credits
        $totalDebits = collect($request->details)->sum('debit_amount');
        $totalCredits = collect($request->details)->sum('credit_amount');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'مجموع بدهکار و بستانکار باید برابر باشد',
            ], 422);
        }

        try {
            $entry = $this->accountingService->createJournalEntry($request->all());

            return response()->json([
                'success' => true,
                'message' => 'سند حسابداری با موفقیت ایجاد شد',
                'data' => $entry->load('details.account'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد سند: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a journal entry
     */
    public function postJournalEntry(JournalEntry $journalEntry): JsonResponse
    {
        if (!$journalEntry->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'فقط اسناد پیش‌نویس قابل ثبت هستند',
            ], 422);
        }

        try {
            $this->accountingService->postJournalEntry($journalEntry);

            return response()->json([
                'success' => true,
                'message' => 'سند با موفقیت ثبت شد',
                'data' => $journalEntry->fresh()->load('details.account'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت سند: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse a journal entry
     */
    public function reverseJournalEntry(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        if (!$journalEntry->isPosted()) {
            return response()->json([
                'success' => false,
                'message' => 'فقط اسناد ثبت شده قابل برگشت هستند',
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reversingEntry = $this->accountingService->reverseJournalEntry(
                $journalEntry, 
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'سند با موفقیت برگشت خورد',
                'data' => [
                    'original_entry' => $journalEntry->fresh(),
                    'reversing_entry' => $reversingEntry->load('details.account'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در برگشت سند: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate trial balance
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : now();

        try {
            $trialBalance = $this->accountingService->generateTrialBalance($asOfDate);

            $totalDebits = $trialBalance->sum('debit_balance');
            $totalCredits = $trialBalance->sum('credit_balance');

            return response()->json([
                'success' => true,
                'data' => [
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                    'accounts' => $trialBalance,
                    'totals' => [
                        'total_debits' => $totalDebits,
                        'total_credits' => $totalCredits,
                        'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تولید تراز آزمایشی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate profit & loss statement
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        try {
            $profitLoss = $this->accountingService->generateProfitLoss($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $profitLoss,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تولید صورت سود و زیان: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate balance sheet
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : now();

        try {
            $balanceSheet = $this->accountingService->generateBalanceSheet($asOfDate);

            return response()->json([
                'success' => true,
                'data' => $balanceSheet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تولید ترازنامه: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate general ledger for an account
     */
    public function generalLedger(Request $request, Account $account): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        try {
            $generalLedger = $this->accountingService->generateGeneralLedger(
                $account->id, 
                $startDate, 
                $endDate
            );

            return response()->json([
                'success' => true,
                'data' => $generalLedger,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تولید دفتر کل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process recurring journal entries
     */
    public function processRecurringEntries(): JsonResponse
    {
        try {
            $processedCount = $this->accountingService->processRecurringEntries();

            return response()->json([
                'success' => true,
                'message' => "تعداد {$processedCount} سند تکراری پردازش شد",
                'data' => ['processed_count' => $processedCount],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در پردازش اسناد تکراری: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initialize standard chart of accounts
     */
    public function initializeChartOfAccounts(): JsonResponse
    {
        try {
            // Check if accounts already exist
            if (Account::count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'بخشی از حساب‌ها از قبل وجود دارد. برای جلوگیری از تداخل، عملیات متوقف شد.',
                ], 422);
            }

            $this->accountingService->createStandardChartOfAccounts();

            return response()->json([
                'success' => true,
                'message' => 'دفتر حساب‌های استاندارد با موفقیت ایجاد شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد دفتر حساب‌ها: ' . $e->getMessage(),
            ], 500);
        }
    }
}