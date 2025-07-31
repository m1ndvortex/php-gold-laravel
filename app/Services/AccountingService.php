<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create a new journal entry
     */
    public function createJournalEntry(array $data): JournalEntry
    {
        DB::beginTransaction();
        
        try {
            $entry = JournalEntry::create([
                'entry_number' => $data['entry_number'] ?? JournalEntry::generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurring_pattern' => $data['recurring_pattern'] ?? null,
                'next_recurring_date' => $data['next_recurring_date'] ?? null,
                'is_system_generated' => $data['is_system_generated'] ?? false,
                'created_by' => auth()->id(),
            ]);

            // Add journal entry details
            if (isset($data['details']) && is_array($data['details'])) {
                foreach ($data['details'] as $detail) {
                    $entry->addDetail(
                        accountId: $detail['account_id'],
                        debitAmount: $detail['debit_amount'] ?? 0,
                        creditAmount: $detail['credit_amount'] ?? 0,
                        description: $detail['description'] ?? null,
                        costCenter: $detail['cost_center'] ?? null
                    );
                }
            }

            DB::commit();
            return $entry;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Post a journal entry
     */
    public function postJournalEntry(JournalEntry $entry): bool
    {
        return $entry->post();
    }

    /**
     * Reverse a journal entry
     */
    public function reverseJournalEntry(JournalEntry $entry, string $reason = null): JournalEntry
    {
        return $entry->reverse($reason);
    }

    /**
     * Create chart of accounts with standard structure
     */
    public function createStandardChartOfAccounts(): void
    {
        DB::beginTransaction();
        
        try {
            // Assets
            $assets = $this->createAccount([
                'code' => '1000',
                'name' => 'دارایی‌ها',
                'name_en' => 'Assets',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            // Current Assets
            $currentAssets = $this->createAccount([
                'code' => '1100',
                'name' => 'دارایی‌های جاری',
                'name_en' => 'Current Assets',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'parent_id' => $assets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '1110',
                'name' => 'صندوق',
                'name_en' => 'Cash',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'parent_id' => $currentAssets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '1120',
                'name' => 'بانک',
                'name_en' => 'Bank',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'parent_id' => $currentAssets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '1200',
                'name' => 'حساب‌های دریافتنی',
                'name_en' => 'Accounts Receivable',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'parent_id' => $currentAssets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '1300',
                'name' => 'موجودی کالا',
                'name_en' => 'Inventory',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'parent_id' => $currentAssets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            // Fixed Assets
            $fixedAssets = $this->createAccount([
                'code' => '1400',
                'name' => 'دارایی‌های ثابت',
                'name_en' => 'Fixed Assets',
                'type' => 'asset',
                'subtype' => 'fixed_asset',
                'parent_id' => $assets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '1410',
                'name' => 'ساختمان و تجهیزات',
                'name_en' => 'Buildings & Equipment',
                'type' => 'asset',
                'subtype' => 'fixed_asset',
                'parent_id' => $fixedAssets->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            // Liabilities
            $liabilities = $this->createAccount([
                'code' => '2000',
                'name' => 'بدهی‌ها',
                'name_en' => 'Liabilities',
                'type' => 'liability',
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            // Current Liabilities
            $currentLiabilities = $this->createAccount([
                'code' => '2100',
                'name' => 'بدهی‌های جاری',
                'name_en' => 'Current Liabilities',
                'type' => 'liability',
                'subtype' => 'current_liability',
                'parent_id' => $liabilities->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '2110',
                'name' => 'حساب‌های پرداختنی',
                'name_en' => 'Accounts Payable',
                'type' => 'liability',
                'subtype' => 'current_liability',
                'parent_id' => $currentLiabilities->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '2300',
                'name' => 'مالیات بر ارزش افزوده پرداختنی',
                'name_en' => 'VAT Payable',
                'type' => 'liability',
                'subtype' => 'current_liability',
                'parent_id' => $currentLiabilities->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            // Equity
            $equity = $this->createAccount([
                'code' => '3000',
                'name' => 'حقوق صاحبان سهام',
                'name_en' => 'Equity',
                'type' => 'equity',
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '3100',
                'name' => 'سرمایه',
                'name_en' => 'Capital',
                'type' => 'equity',
                'subtype' => 'owner_equity',
                'parent_id' => $equity->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '3200',
                'name' => 'سود انباشته',
                'name_en' => 'Retained Earnings',
                'type' => 'equity',
                'subtype' => 'retained_earnings',
                'parent_id' => $equity->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            // Revenue
            $revenue = $this->createAccount([
                'code' => '4000',
                'name' => 'درآمدها',
                'name_en' => 'Revenue',
                'type' => 'revenue',
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '4100',
                'name' => 'درآمد فروش',
                'name_en' => 'Sales Revenue',
                'type' => 'revenue',
                'subtype' => 'operating_revenue',
                'parent_id' => $revenue->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '4200',
                'name' => 'سایر درآمدها',
                'name_en' => 'Other Revenue',
                'type' => 'revenue',
                'subtype' => 'other_revenue',
                'parent_id' => $revenue->id,
                'normal_balance' => 'credit',
                'is_system' => true,
            ]);

            // Expenses
            $expenses = $this->createAccount([
                'code' => '5000',
                'name' => 'هزینه‌ها',
                'name_en' => 'Expenses',
                'type' => 'expense',
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '5100',
                'name' => 'بهای تمام شده کالای فروش رفته',
                'name_en' => 'Cost of Goods Sold',
                'type' => 'expense',
                'subtype' => 'operating_expense',
                'parent_id' => $expenses->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            $this->createAccount([
                'code' => '5200',
                'name' => 'هزینه‌های عملیاتی',
                'name_en' => 'Operating Expenses',
                'type' => 'expense',
                'subtype' => 'operating_expense',
                'parent_id' => $expenses->id,
                'normal_balance' => 'debit',
                'is_system' => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a new account
     */
    public function createAccount(array $data): Account
    {
        return Account::create($data);
    }

    /**
     * Generate Trial Balance report
     */
    public function generateTrialBalance(Carbon $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? now();

        return Account::with('journalEntryDetails.journalEntry')
            ->active()
            ->get()
            ->map(function ($account) use ($asOfDate) {
                $balance = $account->getBalanceForPeriod(
                    Carbon::create(1900, 1, 1),
                    $asOfDate
                );

                return [
                    'account_code' => $account->code,
                    'account_name' => $account->localized_name,
                    'debit_balance' => $account->isDebitAccount() && $balance > 0 ? $balance : 0,
                    'credit_balance' => $account->isCreditAccount() && $balance > 0 ? $balance : 0,
                ];
            })
            ->filter(function ($item) {
                return $item['debit_balance'] > 0 || $item['credit_balance'] > 0;
            });
    }

    /**
     * Generate Profit & Loss statement
     */
    public function generateProfitLoss(Carbon $startDate, Carbon $endDate): array
    {
        $revenue = Account::byType('revenue')
            ->active()
            ->get()
            ->sum(function ($account) use ($startDate, $endDate) {
                return $account->getBalanceForPeriod($startDate, $endDate);
            });

        $expenses = Account::byType('expense')
            ->active()
            ->get()
            ->sum(function ($account) use ($startDate, $endDate) {
                return $account->getBalanceForPeriod($startDate, $endDate);
            });

        $netIncome = $revenue - $expenses;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_income' => $netIncome,
            'revenue_accounts' => Account::byType('revenue')
                ->active()
                ->get()
                ->map(function ($account) use ($startDate, $endDate) {
                    return [
                        'code' => $account->code,
                        'name' => $account->localized_name,
                        'amount' => $account->getBalanceForPeriod($startDate, $endDate),
                    ];
                })
                ->filter(fn($item) => $item['amount'] > 0),
            'expense_accounts' => Account::byType('expense')
                ->active()
                ->get()
                ->map(function ($account) use ($startDate, $endDate) {
                    return [
                        'code' => $account->code,
                        'name' => $account->localized_name,
                        'amount' => $account->getBalanceForPeriod($startDate, $endDate),
                    ];
                })
                ->filter(fn($item) => $item['amount'] > 0),
        ];
    }

    /**
     * Generate Balance Sheet
     */
    public function generateBalanceSheet(Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();

        $assets = Account::byType('asset')
            ->active()
            ->get()
            ->sum(function ($account) use ($asOfDate) {
                return $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate);
            });

        $liabilities = Account::byType('liability')
            ->active()
            ->get()
            ->sum(function ($account) use ($asOfDate) {
                return $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate);
            });

        $equity = Account::byType('equity')
            ->active()
            ->get()
            ->sum(function ($account) use ($asOfDate) {
                return $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate);
            });

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_liabilities_equity' => $liabilities + $equity,
            'asset_accounts' => Account::byType('asset')
                ->active()
                ->get()
                ->map(function ($account) use ($asOfDate) {
                    return [
                        'code' => $account->code,
                        'name' => $account->localized_name,
                        'amount' => $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate),
                    ];
                })
                ->filter(fn($item) => $item['amount'] > 0),
            'liability_accounts' => Account::byType('liability')
                ->active()
                ->get()
                ->map(function ($account) use ($asOfDate) {
                    return [
                        'code' => $account->code,
                        'name' => $account->localized_name,
                        'amount' => $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate),
                    ];
                })
                ->filter(fn($item) => $item['amount'] > 0),
            'equity_accounts' => Account::byType('equity')
                ->active()
                ->get()
                ->map(function ($account) use ($asOfDate) {
                    return [
                        'code' => $account->code,
                        'name' => $account->localized_name,
                        'amount' => $account->getBalanceForPeriod(Carbon::create(1900, 1, 1), $asOfDate),
                    ];
                })
                ->filter(fn($item) => $item['amount'] > 0),
        ];
    }

    /**
     * Generate General Ledger for an account
     */
    public function generateGeneralLedger(int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $account = Account::findOrFail($accountId);
        
        $entries = JournalEntryDetail::with(['journalEntry', 'account'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'posted')
                      ->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->orderBy('created_at')
            ->get();

        $runningBalance = $account->opening_balance;
        $transactions = [];

        foreach ($entries as $entry) {
            $amount = $entry->debit_amount > 0 ? $entry->debit_amount : -$entry->credit_amount;
            
            if ($account->isDebitAccount()) {
                $runningBalance += $amount;
            } else {
                $runningBalance -= $amount;
            }

            $transactions[] = [
                'date' => $entry->journalEntry->entry_date,
                'entry_number' => $entry->journalEntry->entry_number,
                'description' => $entry->description,
                'reference' => $entry->journalEntry->reference,
                'debit_amount' => $entry->debit_amount,
                'credit_amount' => $entry->credit_amount,
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => [
                'code' => $account->code,
                'name' => $account->localized_name,
                'type' => $account->type,
                'opening_balance' => $account->opening_balance,
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'transactions' => $transactions,
            'closing_balance' => $runningBalance,
        ];
    }

    /**
     * Process recurring journal entries
     */
    public function processRecurringEntries(): int
    {
        $processedCount = 0;
        
        $recurringEntries = JournalEntry::recurring()
            ->posted()
            ->where('next_recurring_date', '<=', now()->toDateString())
            ->get();

        foreach ($recurringEntries as $entry) {
            try {
                $newEntry = $entry->createRecurringEntry();
                if ($newEntry) {
                    $processedCount++;
                }
            } catch (\Exception $e) {
                // Log error but continue processing other entries
                \Log::error('Failed to create recurring entry: ' . $e->getMessage(), [
                    'entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                ]);
            }
        }

        return $processedCount;
    }
}