<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create basic permissions and roles for testing
        $this->createBasicRolesAndPermissions();
        
        // Create test tenant
        $this->createTestTenant();
    }

    protected function createBasicRolesAndPermissions(): void
    {
        // Create permissions
        $permissions = [
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'dashboard'],
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Create role
        $role = Role::create([
            'name' => 'test_user',
            'display_name' => 'Test User',
            'description' => 'Test role for authentication tests',
        ]);

        // Assign permissions to role
        $role->permissions()->sync(Permission::all()->pluck('id'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user',
                        'token',
                        'session',
                        'expires_at',
                    ],
                ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'LOGIN_FAILED',
                    ],
                ]);
    }

    public function test_user_cannot_login_when_inactive(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'LOGIN_FAILED',
                    ],
                ]);
    }

    public function test_user_with_2fa_enabled_requires_verification(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Test User 2FA',
            'email' => 'test2fa@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt('TESTSECRET123456'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test2fa@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'requires_2fa' => true,
                    ],
                ])
                ->assertJsonStructure([
                    'data' => [
                        'user_id',
                        'temp_token',
                    ],
                ]);
    }

    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/test/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'name',
                    'email',
                    'role',
                ]);
    }

    public function test_user_can_logout(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/test/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        // Verify token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_user_permissions_are_checked(): void
    {
        $role = Role::where('name', 'test_user')->first();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $this->assertTrue($user->hasPermission('dashboard.view'));
        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('nonexistent.permission'));
    }

    public function test_validation_errors_are_returned(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => '123', // Too short
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                    ],
                ])
                ->assertJsonStructure([
                    'error' => [
                        'details',
                    ],
                ]);
    }

    protected function createTestTenant(): void
    {
        \App\Tenant::create([
            'name' => 'Test Shop',
            'subdomain' => 'testshop',
            'database_name' => 'tenant_testshop',
            'status' => 'active',
        ]);
    }
}
