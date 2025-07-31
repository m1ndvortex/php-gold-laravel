<?php

if (!function_exists('tenant')) {
    /**
     * Get the current tenant instance
     */
    function tenant(): ?\App\Tenant
    {
        return app('tenant');
    }
}

if (!function_exists('tenant_context')) {
    /**
     * Get the current tenant context
     */
    function tenant_context(): ?\App\Tenant
    {
        try {
            return app()->has('tenant') ? app('tenant') : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}