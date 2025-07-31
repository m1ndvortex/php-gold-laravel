<?php

namespace Database\Seeders;

use App\Services\AccountingService;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create standard chart of accounts
        $this->accountingService->createStandardChartOfAccounts();
        
        $this->command->info('Standard chart of accounts created successfully.');
    }
}