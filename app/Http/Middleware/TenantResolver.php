<?php

namespace App\Http\Middleware;

use App\Tenant;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant resolution for certain routes
        if ($this->shouldSkipTenantResolution($request)) {
            return $next($request);
        }

        $subdomain = $this->extractSubdomain($request);
        
        if (!$subdomain) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'No valid subdomain provided'
            ], 404);
        }

        $tenant = Tenant::findBySubdomain($subdomain);
        
        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'Invalid subdomain or inactive tenant'
            ], 404);
        }

        // Set tenant context
        $this->tenantService->setCurrentTenant($tenant);
        
        // Configure database connection
        $tenant->configureDatabaseConnection();
        
        // Update last accessed timestamp
        $tenant->updateLastAccessed();

        // Add tenant to request for easy access
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    /**
     * Extract subdomain from request
     */
    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        
        // Check for X-Tenant-Subdomain header (useful for API testing)
        if ($request->hasHeader('X-Tenant-Subdomain')) {
            return $request->header('X-Tenant-Subdomain');
        }

        // Extract subdomain from host
        $parts = explode('.', $host);
        
        // If we have at least 3 parts (subdomain.domain.tld), return the first part
        if (count($parts) >= 3) {
            return $parts[0];
        }

        // For local development, check for subdomain in the format: subdomain.localhost
        if (count($parts) === 2 && $parts[1] === 'localhost') {
            return $parts[0];
        }

        return null;
    }

    /**
     * Check if tenant resolution should be skipped for this request
     */
    protected function shouldSkipTenantResolution(Request $request): bool
    {
        $skipRoutes = [
            'health',
            'status',
            'admin/*',
            'platform/*'
        ];

        foreach ($skipRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }
}
