<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate 
                            {--tenant= : Specific tenant subdomain to migrate}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migration}
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for tenant databases';

    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantSubdomain = $this->option('tenant');
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');
        $force = $this->option('force');

        if ($tenantSubdomain) {
            $this->migrateSingleTenant($tenantSubdomain, $fresh, $seed, $force);
        } else {
            $this->migrateAllTenants($fresh, $seed, $force);
        }
    }

    /**
     * Migrate a single tenant
     */
    protected function migrateSingleTenant(string $subdomain, bool $fresh, bool $seed, bool $force): void
    {
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            $this->error("Tenant with subdomain '{$subdomain}' not found.");
            return;
        }

        $this->info("Migrating tenant: {$tenant->name} ({$tenant->subdomain})");

        try {
            $this->tenantService->switchToTenant($tenant);
            
            if ($fresh) {
                $this->call('migrate:fresh', [
                    '--database' => $this->tenantService->getTenantConnectionName($tenant),
                    '--path' => 'database/migrations/tenant',
                    '--force' => $force
                ]);
            } else {
                $this->call('migrate', [
                    '--database' => $this->tenantService->getTenantConnectionName($tenant),
                    '--path' => 'database/migrations/tenant',
                    '--force' => $force
                ]);
            }

            if ($seed) {
                $this->call('db:seed', [
                    '--database' => $this->tenantService->getTenantConnectionName($tenant),
                    '--class' => 'TenantSeeder',
                    '--force' => $force
                ]);
            }

            $this->info("✓ Migration completed for tenant: {$tenant->subdomain}");
        } catch (\Exception $e) {
            $this->error("✗ Migration failed for tenant {$tenant->subdomain}: " . $e->getMessage());
        } finally {
            $this->tenantService->switchToMain();
        }
    }

    /**
     * Migrate all tenants
     */
    protected function migrateAllTenants(bool $fresh, bool $seed, bool $force): void
    {
        $tenants = Tenant::where('status', 'active')->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return;
        }

        $this->info("Found {$tenants->count()} active tenant(s). Starting migration...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($tenants as $tenant) {
            $this->info("Migrating: {$tenant->name} ({$tenant->subdomain})");

            try {
                $this->tenantService->switchToTenant($tenant);
                
                if ($fresh) {
                    $this->call('migrate:fresh', [
                        '--database' => $this->tenantService->getTenantConnectionName($tenant),
                        '--path' => 'database/migrations/tenant',
                        '--force' => $force
                    ]);
                } else {
                    $this->call('migrate', [
                        '--database' => $this->tenantService->getTenantConnectionName($tenant),
                        '--path' => 'database/migrations/tenant',
                        '--force' => $force
                    ]);
                }

                if ($seed) {
                    $this->call('db:seed', [
                        '--database' => $this->tenantService->getTenantConnectionName($tenant),
                        '--class' => 'TenantSeeder',
                        '--force' => $force
                    ]);
                }

                $this->info("✓ Completed: {$tenant->subdomain}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ Failed: {$tenant->subdomain} - " . $e->getMessage());
                $failureCount++;
            } finally {
                $this->tenantService->switchToMain();
            }
        }

        $this->info("\nMigration Summary:");
        $this->info("✓ Successful: {$successCount}");
        if ($failureCount > 0) {
            $this->error("✗ Failed: {$failureCount}");
        }
    }
}
