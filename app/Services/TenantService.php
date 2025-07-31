<?php

namespace App\Services;

use App\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class TenantService
{
    public ?Tenant $currentTenant = null;

    /**
     * Set the current tenant
     */
    public function setCurrentTenant(?Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
        
        if ($tenant) {
            app()->instance('tenant', $tenant);
        } else {
            app()->forgetInstance('tenant');
        }
    }

    /**
     * Get the current tenant
     */
    public function getCurrentTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Create a new tenant with database
     */
    public function createTenant(array $data): Tenant
    {
        // Generate unique database name
        $databaseName = 'tenant_' . uniqid();
        
        $tenant = Tenant::create([
            'name' => $data['name'],
            'subdomain' => $data['subdomain'],
            'database_name' => $databaseName,
            'status' => $data['status'] ?? 'active',
            'subscription_plan' => $data['subscription_plan'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);

        // Create the database
        if (!$tenant->createDatabase()) {
            $tenant->delete();
            throw new \Exception('Failed to create tenant database');
        }

        // Run migrations
        if (!$tenant->runMigrations()) {
            $tenant->dropDatabase();
            $tenant->delete();
            throw new \Exception('Failed to run tenant migrations');
        }

        return $tenant;
    }

    /**
     * Delete a tenant and its database
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        try {
            // Drop the database
            $tenant->dropDatabase();
            
            // Delete the tenant record
            $tenant->delete();
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete tenant {$tenant->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Switch to tenant database connection
     */
    public function switchToTenant(Tenant $tenant): void
    {
        $this->setCurrentTenant($tenant);
        $tenant->configureDatabaseConnection();
    }

    /**
     * Switch back to main database connection
     */
    public function switchToMain(): void
    {
        Config::set('database.default', 'mysql');
        DB::purge('mysql');
        DB::reconnect('mysql');
        $this->currentTenant = null;
        app()->forgetInstance('tenant');
    }

    /**
     * Run migrations for a specific tenant
     */
    public function runTenantMigrations(Tenant $tenant): bool
    {
        try {
            $this->switchToTenant($tenant);
            
            Artisan::call('migrate', [
                '--database' => 'tenant_' . $tenant->id,
                '--path' => 'database/migrations/tenant',
                '--force' => true
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to run migrations for tenant {$tenant->id}: " . $e->getMessage());
            return false;
        } finally {
            $this->switchToMain();
        }
    }

    /**
     * Run migrations for all tenants
     */
    public function runAllTenantMigrations(): array
    {
        $results = [];
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $results[$tenant->subdomain] = $this->runTenantMigrations($tenant);
        }

        return $results;
    }

    /**
     * Get tenant database connection name
     */
    public function getTenantConnectionName(Tenant $tenant): string
    {
        return 'tenant_' . $tenant->id;
    }

    /**
     * Check if tenant database exists
     */
    public function tenantDatabaseExists(Tenant $tenant): bool
    {
        try {
            $mainConnection = DB::connection('mysql');
            $result = $mainConnection->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenant->database_name]);
            
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate subdomain format
     */
    public function validateSubdomain(string $subdomain): bool
    {
        // Check format: only lowercase letters, numbers, and hyphens
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return false;
        }

        // Check length (3-63 characters)
        if (strlen($subdomain) < 3 || strlen($subdomain) > 63) {
            return false;
        }

        // Cannot start or end with hyphen
        if (str_starts_with($subdomain, '-') || str_ends_with($subdomain, '-')) {
            return false;
        }

        // Check for reserved subdomains
        $reserved = ['www', 'api', 'admin', 'app', 'mail', 'ftp', 'localhost', 'staging', 'test'];
        if (in_array($subdomain, $reserved)) {
            return false;
        }

        return true;
    }
}