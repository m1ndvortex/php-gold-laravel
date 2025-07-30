<?php

namespace App\Providers;

use App\Services\TenantService;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Register tenant helper
        $this->app->bind('tenant', function ($app) {
            $tenantService = $app->make(TenantService::class);
            return $tenantService->currentTenant ?? null;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
