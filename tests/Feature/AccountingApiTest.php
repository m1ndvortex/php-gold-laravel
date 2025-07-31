<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class AccountingApiTest extends TenantTestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_chart_of_accounts()
    {
        Account::factory()->count(5)->create();

        $response = $this->getJson('/api/accounting/chart-of-accounts');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'type',
                            'current_balance',
                            'normal_balance',
                            'has_children',
                        ]
                    ]
                ]);
    }

    public function test_can_filter_chart_of_accounts_by_type()
    {
        Account::factory()->asset()->create();
        Account::factory()->liability()->create();

        $response = $this->getJson('/api/accounting/chart-of-accounts?type=asset');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('asset', $data[0]['type']);
    }

    public function test_can_create_account()
    {
        $accountData = [
            'name' => 'حساب تست',
            'name_en' => 'Test Account',
            'description' => 'توضیحات تست',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'opening_balance' => 5000,
        ];

        $response = $this->postJson('/api/accounting/accounts', $accountData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'حساب با موفقیت ایجاد شد',
                ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'حساب تست',
            'type' => 'asset',
            'opening_balance' => 5000,
        ]);
    }

    public function test_cannot_create_account_with_invalid_data()
    {
        $response = $this->postJson('/api/accounting/accounts', [
            'name' => '', // Required field missing
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_update_account()
    {
        $account = Account::factory()->create(['is_system' => false]);

        $updateData = [
            'name' => 'نام جدید',
            'name_en' => 'New Name',
            'description' => 'توضیحات جدید',
            'is_active' => false,
        ];

        $response = $this->putJson("/api/accounting/accounts/{$account->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'حساب با موفقیت به‌روزرسانی شد',
                ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'نام جدید',
            'is_active' => false,
        ]);
    }

    public function test_cannot_update_system_account()
    {
        $account = Account::factory()->create(['is_system' => true]);

        $response = $this->putJson("/api/accounting/accounts/{$account->id}", [
            'name' => 'نام جدید',
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'حساب‌های سیستمی قابل ویرایش نیستند',
                ]);
    }

    public function test_can_get_journal_entries()
    {
        JournalEntry::factory()->count(3)->create();

        $response = $this->getJson('/api/accounting/journal-entries');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'entry_number',
                                'entry_date',
                                'description',
                                'total_debit',
                                'total_credit',
                                'status',
                            ]
                        ]
                    ]
                ]);
    }

    public function test_can_filter_journal_entries_by_status()
    {
        JournalEntry::factory()->draft()->create();
        JournalEntry::factory()->posted()->create();

        $response = $this->getJson('/api/accounting/journal-entries?status=draft');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('draft', $data[0]['status']);
    }

    public function test_can_create_journal_entry()
    {
        $cashAccount = Account::factory()->create();
        $salesAccount = Account::factory()->create();

        $entryData = [
            'entry_date' => '2024-01-15',
            'description' => 'Test journal entry',
            'details' => [
                [
                    'account_id' => $cashAccount->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                    'description' => 'Cash received',
                ],
                [
                    'account_id' => $salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 1000,
                    'description' => 'Sales revenue',
                ],
            ],
        ];

        $response = $this->postJson('/api/accounting/journal-entries', $entryData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'سند حسابداری با موفقیت ایجاد شد',
                ]);

        $this->assertDatabaseHas('journal_entries', [
            'description' => 'Test journal entry',
            'total_debit' => 1000,
            'total_credit' => 1000,
        ]);
    }

    public function test_cannot_create_unbalanced_journal_entry()
    {
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();

        $entryData = [
            'entry_date' => '2024-01-15',
            'description' => 'Unbalanced entry',
            'details' => [
                [
                    'account_id' => $account1->id,
                    'debit_amount' => 1000,
                    'credit_amount' => 0,
                ],
                [
                    'account_id' => $account2->id,
                    'debit_amount' => 0,
                    'credit_amount' => 500, // Unbalanced
                ],
            ],
        ];

        $response = $this->postJson('/api/accounting/journal-entries', $entryData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'مجموع بدهکار و بستانکار باید برابر باشد',
                ]);
    }

    public function test_can_post_journal_entry()
    {
        $entry = JournalEntry::factory()->draft()->create();
        JournalEntryDetail::factory()->debit(500)->forJournalEntry($entry)->create();
        JournalEntryDetail::factory()->credit(500)->forJournalEntry($entry)->create();
        $entry->recalculateTotals();

        $response = $this->postJson("/api/accounting/journal-entries/{$entry->id}/post");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'سند با موفقیت ثبت شد',
                ]);

        $this->assertEquals('posted', $entry->fresh()->status);
    }

    public function test_cannot_post_already_posted_entry()
    {
        $entry = JournalEntry::factory()->posted()->create();

        $response = $this->postJson("/api/accounting/journal-entries/{$entry->id}/post");

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'فقط اسناد پیش‌نویس قابل ثبت هستند',
                ]);
    }

    public function test_can_reverse_journal_entry()
    {
        $entry = JournalEntry::factory()->posted()->create();
        JournalEntryDetail::factory()->debit(500)->forJournalEntry($entry)->create();
        JournalEntryDetail::factory()->credit(500)->forJournalEntry($entry)->create();

        $response = $this->postJson("/api/accounting/journal-entries/{$entry->id}/reverse", [
            'reason' => 'Test reversal reason',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'سند با موفقیت برگشت خورد',
                ]);

        $this->assertEquals('reversed', $entry->fresh()->status);
        $this->assertEquals(2, JournalEntry::count()); // Original + reversing entry
    }

    public function test_can_get_trial_balance()
    {
        Account::factory()->create([
            'code' => '1110',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'current_balance' => 5000,
        ]);

        $response = $this->getJson('/api/accounting/reports/trial-balance');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'as_of_date',
                        'accounts' => [
                            '*' => [
                                'account_code',
                                'account_name',
                                'debit_balance',
                                'credit_balance',
                            ]
                        ],
                        'totals' => [
                            'total_debits',
                            'total_credits',
                            'is_balanced',
                        ]
                    ]
                ]);
    }

    public function test_can_get_profit_loss()
    {
        Account::factory()->revenue()->create(['current_balance' => 10000]);
        Account::factory()->expense()->create(['current_balance' => 6000]);

        $response = $this->getJson('/api/accounting/reports/profit-loss?start_date=2024-01-01&end_date=2024-01-31');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'period',
                        'revenue',
                        'expenses',
                        'net_income',
                        'revenue_accounts',
                        'expense_accounts',
                    ]
                ]);
    }

    public function test_profit_loss_requires_date_range()
    {
        $response = $this->getJson('/api/accounting/reports/profit-loss');

        $response->assertStatus(422);
    }

    public function test_can_get_balance_sheet()
    {
        Account::factory()->asset()->create(['current_balance' => 15000]);
        Account::factory()->liability()->create(['current_balance' => 8000]);
        Account::factory()->equity()->create(['current_balance' => 7000]);

        $response = $this->getJson('/api/accounting/reports/balance-sheet');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'as_of_date',
                        'assets',
                        'liabilities',
                        'equity',
                        'total_liabilities_equity',
                        'asset_accounts',
                        'liability_accounts',
                        'equity_accounts',
                    ]
                ]);
    }

    public function test_can_get_general_ledger()
    {
        $account = Account::factory()->create();
        
        $entry = JournalEntry::factory()->posted()->create();
        JournalEntryDetail::factory()->debit(1000)->forAccount($account)->forJournalEntry($entry)->create();

        $response = $this->getJson("/api/accounting/accounts/{$account->id}/general-ledger?start_date=2024-01-01&end_date=2024-01-31");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'account',
                        'period',
                        'transactions' => [
                            '*' => [
                                'date',
                                'entry_number',
                                'description',
                                'debit_amount',
                                'credit_amount',
                                'balance',
                            ]
                        ],
                        'closing_balance',
                    ]
                ]);
    }

    public function test_general_ledger_requires_date_range()
    {
        $account = Account::factory()->create();

        $response = $this->getJson("/api/accounting/accounts/{$account->id}/general-ledger");

        $response->assertStatus(422);
    }

    public function test_can_process_recurring_entries()
    {
        $recurringEntry = JournalEntry::factory()->posted()->recurring()->create([
            'next_recurring_date' => Carbon::yesterday(),
        ]);

        JournalEntryDetail::factory()->debit(1000)->forJournalEntry($recurringEntry)->create();
        JournalEntryDetail::factory()->credit(1000)->forJournalEntry($recurringEntry)->create();

        $response = $this->postJson('/api/accounting/recurring-entries/process');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['processed_count']
                ]);

        $this->assertEquals(2, JournalEntry::count());
    }

    public function test_can_initialize_chart_of_accounts()
    {
        $response = $this->postJson('/api/accounting/initialize-chart');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'دفتر حساب‌های استاندارد با موفقیت ایجاد شد',
                ]);

        $this->assertDatabaseHas('accounts', ['code' => '1000', 'type' => 'asset']);
        $this->assertDatabaseHas('accounts', ['code' => '4100', 'name' => 'درآمد فروش']);
    }

    public function test_cannot_initialize_chart_when_accounts_exist()
    {
        Account::factory()->create();

        $response = $this->postJson('/api/accounting/initialize-chart');

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'بخشی از حساب‌ها از قبل وجود دارد. برای جلوگیری از تداخل، عملیات متوقف شد.',
                ]);
    }

    public function test_requires_authentication()
    {
        $this->withoutMiddleware();
        
        $response = $this->getJson('/api/accounting/chart-of-accounts');
        
        $response->assertStatus(401);
    }
}