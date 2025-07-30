<?php

namespace Tests\Feature;

use App\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    protected TenantService $tenantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantService = app(TenantService::class);
    }

    public function test_tenant_model_creation(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Jewelry Store',
            'subdomain' => 'test-store',
            'database_name' => 'tenant_test_123',
            'status' => 'active',
            'subscription_plan' => 'premium',
            'settings' => ['theme' => 'dark', 'language' => 'fa']
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Jewelry Store',
            'subdomain' => 'test-store',
            'status' => 'active'
        ]);

        $this->assertEquals('dark', $tenant->settings['theme']);
        $this->assertEquals('fa', $tenant->settings['language']);
    }

    public function test_find_tenant_by_subdomain(): void
    {
        Tenant::create([
            'name' => 'Active Store',
            'subdomain' => 'active-store',
            'database_name' => 'tenant_active_123',
            'status' => 'active'
        ]);

        Tenant::create([
            'name' => 'Inactive Store',
            'subdomain' => 'inactive-store',
            'database_name' => 'tenant_inactive_123',
            'status' => 'inactive'
        ]);

        $activeTenant = Tenant::findBySubdomain('active-store');
        $inactiveTenant = Tenant::findBySubdomain('inactive-store');

        $this->assertNotNull($activeTenant);
        $this->assertEquals('Active Store', $activeTenant->name);
        
        // Should not find inactive tenant
        $this->assertNull($inactiveTenant);
    }

    public function test_tenant_service_validation(): void
    {
        // Valid subdomains
        $this->assertTrue($this->tenantService->validateSubdomain('valid-store'));
        $this->assertTrue($this->tenantService->validateSubdomain('store123'));
        $this->assertTrue($this->tenantService->validateSubdomain('my-jewelry-shop'));

        // Invalid subdomains
        $this->assertFalse($this->tenantService->validateSubdomain('ab')); // too short
        $this->assertFalse($this->tenantService->validateSubdomain('-invalid')); // starts with hyphen
        $this->assertFalse($this->tenantService->validateSubdomain('invalid-')); // ends with hyphen
        $this->assertFalse($this->tenantService->validateSubdomain('Invalid')); // uppercase
        $this->assertFalse($this->tenantService->validateSubdomain('www')); // reserved
        $this->assertFalse($this->tenantService->validateSubdomain('admin')); // reserved
    }

    public function test_tenant_resolver_middleware_subdomain_extraction(): void
    {
        $middleware = new \App\Http\Middleware\TenantResolver($this->tenantService);
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('extractSubdomain');
        $method->setAccessible(true);

        // Test subdomain.domain.com format
        $request = Request::create('http://jewelry-store.example.com/api/test');
        $subdomain = $method->invoke($middleware, $request);
        $this->assertEquals('jewelry-store', $subdomain);

        // Test localhost format
        $request = Request::create('http://test-store.localhost/api/test');
        $subdomain = $method->invoke($middleware, $request);
        $this->assertEquals('test-store', $subdomain);

        // Test header-based tenant identification
        $request = Request::create('http://example.com/api/test');
        $request->headers->set('X-Tenant-Subdomain', 'header-tenant');
        $subdomain = $method->invoke($middleware, $request);
        $this->assertEquals('header-tenant', $subdomain);

        // Test no subdomain
        $request = Request::create('http://example.com/api/test');
        $subdomain = $method->invoke($middleware, $request);
        $this->assertNull($subdomain);
    }

    public function test_tenant_resolver_middleware_skip_routes(): void
    {
        $middleware = new \App\Http\Middleware\TenantResolver($this->tenantService);
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('shouldSkipTenantResolution');
        $method->setAccessible(true);

        // Should skip admin routes
        $request = Request::create('http://example.com/admin/dashboard');
        $this->assertTrue($method->invoke($middleware, $request));

        // Should skip health check
        $request = Request::create('http://example.com/health');
        $this->assertTrue($method->invoke($middleware, $request));

        // Should not skip regular routes
        $request = Request::create('http://example.com/api/invoices');
        $this->assertFalse($method->invoke($middleware, $request));
    }

    public function test_tenant_service_current_tenant_management(): void
    {
        $tenant = Tenant::create([
            'name' => 'Current Tenant Test',
            'subdomain' => 'current-test',
            'database_name' => 'tenant_current_123',
            'status' => 'active'
        ]);

        // Initially no current tenant
        $this->assertNull($this->tenantService->getCurrentTenant());

        // Set current tenant
        $this->tenantService->setCurrentTenant($tenant);
        $this->assertEquals($tenant->id, $this->tenantService->getCurrentTenant()->id);

        // Test helper function
        $this->assertEquals($tenant->id, tenant()?->id);
    }

    public function test_tenant_connection_name_generation(): void
    {
        $tenant = Tenant::create([
            'name' => 'Connection Test',
            'subdomain' => 'connection-test',
            'database_name' => 'tenant_connection_123',
            'status' => 'active'
        ]);

        $connectionName = $this->tenantService->getTenantConnectionName($tenant);
        $this->assertEquals('tenant_' . $tenant->id, $connectionName);
    }
}
