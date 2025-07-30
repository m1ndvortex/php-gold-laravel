<?php

namespace App\Console\Commands;

use App\Services\TenantService;
use Illuminate\Console\Command;

class TenantCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create 
                            {name : The tenant name}
                            {subdomain : The tenant subdomain}
                            {--plan= : Subscription plan}
                            {--status=active : Tenant status (active, inactive, suspended)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant with database and run migrations';

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
        $name = $this->argument('name');
        $subdomain = $this->argument('subdomain');
        $plan = $this->option('plan');
        $status = $this->option('status');

        // Validate subdomain
        if (!$this->tenantService->validateSubdomain($subdomain)) {
            $this->error('Invalid subdomain format. Use only lowercase letters, numbers, and hyphens (3-63 characters).');
            return 1;
        }

        // Check if subdomain already exists
        if (\App\Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Tenant with subdomain '{$subdomain}' already exists.");
            return 1;
        }

        $this->info("Creating tenant: {$name} ({$subdomain})");

        try {
            $tenant = $this->tenantService->createTenant([
                'name' => $name,
                'subdomain' => $subdomain,
                'subscription_plan' => $plan,
                'status' => $status,
            ]);

            $this->info("✓ Tenant created successfully!");
            $this->info("  ID: {$tenant->id}");
            $this->info("  Name: {$tenant->name}");
            $this->info("  Subdomain: {$tenant->subdomain}");
            $this->info("  Database: {$tenant->database_name}");
            $this->info("  Status: {$tenant->status}");

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Failed to create tenant: " . $e->getMessage());
            return 1;
        }
    }
}
