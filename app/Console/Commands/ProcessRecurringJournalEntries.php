<?php

namespace App\Console\Commands;

use App\Services\AccountingService;
use App\Services\TenantService;
use Illuminate\Console\Command;

class ProcessRecurringJournalEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:process-recurring-entries {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recurring journal entries for all tenants or a specific tenant';

    public function __construct(
        private TenantService $tenantService,
        private AccountingService $accountingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            // Process for specific tenant
            $tenant = $this->tenantService->findTenant($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return 1;
            }
            
            $this->processTenantRecurringEntries($tenant);
        } else {
            // Process for all tenants
            $tenants = $this->tenantService->getAllTenants();
            
            $this->info("Processing recurring journal entries for " . $tenants->count() . " tenants...");
            
            foreach ($tenants as $tenant) {
                $this->processTenantRecurringEntries($tenant);
            }
        }

        $this->info('Recurring journal entries processing completed.');
        return 0;
    }

    /**
     * Process recurring entries for a specific tenant
     */
    private function processTenantRecurringEntries($tenant): void
    {
        try {
            // Switch to tenant database
            $this->tenantService->setTenantContext($tenant);
            
            $processedCount = $this->accountingService->processRecurringEntries();
            
            if ($processedCount > 0) {
                $this->info("Tenant {$tenant->name}: Processed {$processedCount} recurring entries.");
            } else {
                $this->line("Tenant {$tenant->name}: No recurring entries to process.");
            }
        } catch (\Exception $e) {
            $this->error("Error processing tenant {$tenant->name}: " . $e->getMessage());
        }
    }
}