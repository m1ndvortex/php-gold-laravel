<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * Authentication System Unit Tests
 * 
 * Note: Some tests may show deprecation warnings from third-party packages:
 * - Laravel Sanctum v3.3.3 has deprecated implicit nullable parameters
 * - jenssegers/agent v2.6.4 has deprecated implicit nullable parameters
 * These are known issues in the dependencies, not our code.
 */
class AuthServiceTest extends TestCase
{
    public function test_auth_service_class_exists(): void
    {
        $this->assertTrue(class_exists(AuthService::class));
    }

    public function test_user_model_has_required_methods(): void
    {
        // Note: This test may trigger deprecation warnings from Laravel Sanctum
        // when the User class (which uses HasApiTokens trait) is loaded
        $customMethods = [
            'hasPermission',
            'hasAnyPermission', 
            'hasAllPermissions',
            'isActive',
            'enableTwoFactor',
            'disableTwoFactor'
        ];
        
        foreach ($customMethods as $method) {
            $this->assertTrue(method_exists(User::class, $method), "Method {$method} does not exist on User model");
        }
    }

    public function test_role_model_has_required_methods(): void
    {
        $this->assertTrue(method_exists(Role::class, 'hasPermission'));
        $this->assertTrue(method_exists(Role::class, 'givePermission'));
        $this->assertTrue(method_exists(Role::class, 'revokePermission'));
        $this->assertTrue(method_exists(Role::class, 'syncPermissions'));
        $this->assertTrue(method_exists(Role::class, 'isActive'));
        $this->assertTrue(method_exists(Role::class, 'isSystemRole'));
    }

    public function test_permission_model_has_required_methods(): void
    {
        $this->assertTrue(method_exists(Permission::class, 'isActive'));
        $this->assertTrue(method_exists(Permission::class, 'getGroupedPermissions'));
    }

    public function test_authentication_system_structure(): void
    {
        // Test that all required classes exist
        $requiredClasses = [
            AuthService::class,
            User::class,
            Role::class,
            Permission::class,
            \App\Services\TwoFactorService::class,
            \App\Http\Middleware\CheckPermission::class,
            \App\Http\Middleware\CheckRole::class,
            \App\Policies\UserPolicy::class,
        ];

        foreach ($requiredClasses as $class) {
            $this->assertTrue(class_exists($class), "Class {$class} does not exist");
        }
    }
}