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