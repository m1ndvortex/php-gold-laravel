<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class AccountingServiceTest extends TenantTestCase
{
    use RefreshDatabase;

    private AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountingService = app(AccountingService::class);
    }

    public function test_can_create_standard_chart_of_accounts()
    {
        $this->accountingService->createStandardChartOfAccounts();

        // Check that main account types are created
        $this->assertDatabaseHas('accounts', ['code' => '1000', 'type' => 'asset']);
        $this->assertDatabaseHas('accounts', ['code' => '2000', 'type' => 'liability']);
        $this->assertDatabaseHas('accounts', ['code' => '3000', 'type' => 'equity']);
        $this->assertDatabaseHas('accounts', ['code' => '4000', 'type' => 'revenue']);
        $this->assertDatabaseHas('accounts', ['code' => '5000', 'type' => 'expense']);

        // Check specific accounts
        $this->assertDatabaseHas('accounts', ['code' => '1110', 'name' => 'صندوق']);
        $this->assertDatabaseHas('accounts', ['code' => '1200', 'name' => 'حساب‌های دریافتنی']);
        $this->assertDatabaseHas('accounts', ['code' => '4100', 'name' => 'درآمد فروش']);
    }

    public function test_can_create_account()
    {
        $accountData = [
            'code' => '1500',
            'name' => 'حساب تست',
            'name_en' => 'Test Account',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ];

        $account = $this->accountingService->createAccount($accountData);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertEquals('1500', $account->code);
        $this->assertEquals('حساب تست', $account->name);
        $this->assertEquals('asset', $account->type);
    }

    public function test_can_create_journal_entry()
    {
        // Create test accounts
        $cashAccount = Account::factory()->create(['code' => '1110', 'type' => 'asset']);
        $salesAccount = Account::factory()->create(['code' => '4100', 'type' => 'revenue']);

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

        $entry = $this->accountingService->createJournalEntry($entryData);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals('Test journal entry', $entry->description);
        $this->assertEquals(1000, $entry->total_debit);
        $this->assertEquals(1000, $entry->total_credit);
        $this->assertTrue($entry->isBalanced());
        $this->assertCount(2, $entry->details);
    }

    public function test_can_post_journal_entry()
    {
        $entry = JournalEntry::factory()->draft()->create();
        JournalEntryDetail::factory()->debit(500)->forJournalEntry($entry)->create();
        JournalEntryDetail::factory()->credit(500)->forJournalEntry($entry)->create();
        $entry->recalculateTotals();

        $result = $this->accountingService->postJournalEntry($entry);

        $this->assertTrue($result);
        $this->assertTrue($entry->fresh()->isPosted());
        $this->assertNotNull($entry->fresh()->posted_at);
    }

    public function test_can_reverse_journal_entry()
    {
        $entry = JournalEntry::factory()->posted()->create();
        JournalEntryDetail::factory()->debit(500)->forJournalEntry($entry)->create();
        JournalEntryDetail::factory()->credit(500)->forJournalEntry($entry)->create();

        $reversingEntry = $this->accountingService->reverseJournalEntry($entry, 'Test reversal');

        $this->assertInstanceOf(JournalEntry::class, $reversingEntry);
        $this->assertTrue($entry->fresh()->isReversed());
        $this->assertTrue($reversingEntry->isPosted());
        $this->assertStringContains('Reversing entry for:', $reversingEntry->description);
    }

    public function test_can_generate_trial_balance()
    {
        // Create accounts with balances
        $cashAccount = Account::factory()->create([
            'code' => '1110',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'current_balance' => 5000,
        ]);

        $salesAccount = Account::factory()->create([
            'code' => '4100',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'current_balance' => 3000,
        ]);

        $trialBalance = $this->accountingService->generateTrialBalance();

        $this->assertCount(2, $trialBalance);
        
        $cashBalance = $trialBalance->firstWhere('account_code', '1110');
        $this->assertEquals(5000, $cashBalance['debit_balance']);
        $this->assertEquals(0, $cashBalance['credit_balance']);

        $salesBalance = $trialBalance->firstWhere('account_code', '4100');
        $this->assertEquals(0, $salesBalance['debit_balance']);
        $this->assertEquals(3000, $salesBalance['credit_balance']);
    }

    public function test_can_generate_profit_loss()
    {
        // Create revenue and expense accounts
        $salesAccount = Account::factory()->revenue()->create(['current_balance' => 10000]);
        $expenseAccount = Account::factory()->expense()->create(['current_balance' => 6000]);

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        $profitLoss = $this->accountingService->generateProfitLoss($startDate, $endDate);

        $this->assertArrayHasKey('revenue', $profitLoss);
        $this->assertArrayHasKey('expenses', $profitLoss);
        $this->assertArrayHasKey('net_income', $profitLoss);
        $this->assertArrayHasKey('period', $profitLoss);
    }

    public function test_can_generate_balance_sheet()
    {
        // Create accounts for balance sheet
        Account::factory()->asset()->create(['current_balance' => 15000]);
        Account::factory()->liability()->create(['current_balance' => 8000]);
        Account::factory()->equity()->create(['current_balance' => 7000]);

        $balanceSheet = $this->accountingService->generateBalanceSheet();

        $this->assertArrayHasKey('assets', $balanceSheet);
        $this->assertArrayHasKey('liabilities', $balanceSheet);
        $this->assertArrayHasKey('equity', $balanceSheet);
        $this->assertArrayHasKey('total_liabilities_equity', $balanceSheet);
    }

    public function test_can_generate_general_ledger()
    {
        $account = Account::factory()->create();
        
        // Create journal entries for the account
        $entry1 = JournalEntry::factory()->posted()->create(['entry_date' => '2024-01-15']);
        JournalEntryDetail::factory()->debit(1000)->forAccount($account)->forJournalEntry($entry1)->create();

        $entry2 = JournalEntry::factory()->posted()->create(['entry_date' => '2024-01-20']);
        JournalEntryDetail::factory()->credit(500)->forAccount($account)->forJournalEntry($entry2)->create();

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        $generalLedger = $this->accountingService->generateGeneralLedger($account->id, $startDate, $endDate);

        $this->assertArrayHasKey('account', $generalLedger);
        $this->assertArrayHasKey('transactions', $generalLedger);
        $this->assertArrayHasKey('closing_balance', $generalLedger);
        $this->assertCount(2, $generalLedger['transactions']);
    }

    public function test_can_process_recurring_entries()
    {
        // Create a recurring entry that's due
        $recurringEntry = JournalEntry::factory()->posted()->recurring()->create([
            'next_recurring_date' => Carbon::yesterday(),
        ]);

        JournalEntryDetail::factory()->debit(1000)->forJournalEntry($recurringEntry)->create();
        JournalEntryDetail::factory()->credit(1000)->forJournalEntry($recurringEntry)->create();

        $processedCount = $this->accountingService->processRecurringEntries();

        $this->assertEquals(1, $processedCount);
        
        // Check that a new entry was created
        $this->assertEquals(2, JournalEntry::count());
        
        // Check that the next recurring date was updated
        $this->assertGreaterThan(Carbon::yesterday(), $recurringEntry->fresh()->next_recurring_date);
    }

    public function test_journal_entry_must_be_balanced()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entry is not balanced');

        $entry = JournalEntry::factory()->draft()->create();
        JournalEntryDetail::factory()->debit(1000)->forJournalEntry($entry)->create();
        JournalEntryDetail::factory()->credit(500)->forJournalEntry($entry)->create(); // Unbalanced
        $entry->recalculateTotals();

        $this->accountingService->postJournalEntry($entry);
    }

    public function test_can_only_reverse_posted_entries()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only posted entries can be reversed');

        $entry = JournalEntry::factory()->draft()->create();
        $this->accountingService->reverseJournalEntry($entry);
    }

    public function test_account_balance_calculation()
    {
        $account = Account::factory()->create([
            'type' => 'asset',
            'normal_balance' => 'debit',
            'opening_balance' => 1000,
        ]);

        // Create journal entries affecting this account
        $entry1 = JournalEntry::factory()->posted()->create();
        JournalEntryDetail::factory()->debit(500)->forAccount($account)->forJournalEntry($entry1)->create();

        $entry2 = JournalEntry::factory()->posted()->create();
        JournalEntryDetail::factory()->credit(200)->forAccount($account)->forJournalEntry($entry2)->create();

        $calculatedBalance = $account->calculateBalance();
        
        // For debit account: opening_balance + debits - credits = 1000 + 500 - 200 = 1300
        $this->assertEquals(1300, $calculatedBalance);
    }
}