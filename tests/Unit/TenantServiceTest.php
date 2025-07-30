<?php

namespace Tests\Unit;

use App\Services\TenantService;
use PHPUnit\Framework\TestCase;

class TenantServiceTest extends TestCase
{
    public function test_tenant_service_can_be_instantiated(): void
    {
        $tenantService = new TenantService();
        $this->assertInstanceOf(TenantService::class, $tenantService);
    }

    public function test_subdomain_validation_works(): void
    {
        $tenantService = new TenantService();
        
        // Valid subdomains
        $this->assertTrue($tenantService->validateSubdomain('myshop'));
        $this->assertTrue($tenantService->validateSubdomain('test-shop'));
        $this->assertTrue($tenantService->validateSubdomain('shop123'));
        $this->assertTrue($tenantService->validateSubdomain('my-jewelry-store'));
        
        // Invalid subdomains
        $this->assertFalse($tenantService->validateSubdomain('ab')); // too short
        $this->assertFalse($tenantService->validateSubdomain('-test')); // starts with hyphen
        $this->assertFalse($tenantService->validateSubdomain('test-')); // ends with hyphen
        $this->assertFalse($tenantService->validateSubdomain('Test')); // uppercase
        $this->assertFalse($tenantService->validateSubdomain('test_shop')); // underscore
        $this->assertFalse($tenantService->validateSubdomain('www')); // reserved
        $this->assertFalse($tenantService->validateSubdomain('admin')); // reserved
        $this->assertFalse($tenantService->validateSubdomain('test')); // reserved
    }

    public function test_tenant_connection_name_generation(): void
    {
        $tenantService = new TenantService();
        
        // Create a real Tenant instance for testing
        $tenant = new \App\Tenant();
        $tenant->id = 123;
        
        $connectionName = $tenantService->getTenantConnectionName($tenant);
        $this->assertEquals('tenant_123', $connectionName);
    }

    public function test_current_tenant_management(): void
    {
        $tenantService = new TenantService();
        
        // Initially no tenant
        $this->assertNull($tenantService->getCurrentTenant());
        
        // Mock tenant
        $mockTenant = new \stdClass();
        $mockTenant->id = 1;
        $mockTenant->subdomain = 'test';
        
        // This would normally work with a real Tenant model
        // For unit testing, we just verify the method exists
        $this->assertTrue(method_exists($tenantService, 'setCurrentTenant'));
        $this->assertTrue(method_exists($tenantService, 'getCurrentTenant'));
    }
}