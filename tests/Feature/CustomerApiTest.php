<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TenantTestCase;

class CustomerApiTest extends TenantTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_list_customers(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        Customer::factory(5)->create(['customer_group_id' => $group->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'phone',
                            'email',
                            'customer_type',
                            'credit_limit',
                            'current_balance',
                            'is_active',
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_create_customer(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();

        $customerData = [
            'name' => 'احمد محمدی',
            'phone' => '09123456789',
            'email' => 'ahmad@example.com',
            'customer_type' => 'individual',
            'customer_group_id' => $group->id,
            'credit_limit' => 5000,
            'birth_date' => '1990-01-01',
            'tags' => ['VIP', 'Regular'],
            'contact_preferences' => ['email', 'sms'],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'customer_type',
                    'group',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'احمد محمدی',
            'phone' => '09123456789',
            'email' => 'ahmad@example.com',
        ]);
    }

    public function test_can_show_customer_details(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'group',
                    'invoices',
                    'ledger_entries',
                ]
            ]);
    }

    public function test_can_update_customer(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '09987654321',
            'customer_type' => 'business',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/customers/{$customer->id}", array_merge($customer->toArray(), $updateData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Customer updated successfully',
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'phone' => '09987654321',
        ]);
    }

    public function test_can_delete_customer(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Customer should be marked as inactive, not deleted
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_can_get_customer_statistics(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        Customer::factory(10)->create(['customer_group_id' => $group->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customers/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_customers',
                    'new_this_month',
                    'credit_limit_exceeded',
                    'upcoming_birthdays',
                    'top_customers',
                ]
            ]);
    }

    public function test_can_get_upcoming_birthdays(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        
        // Create customer with birthday in 3 days
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(3),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customers/birthdays/upcoming?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'birth_date',
                    ]
                ]
            ]);
    }

    public function test_can_create_ledger_entry(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $ledgerData = [
            'transaction_type' => 'credit',
            'amount' => 1000,
            'gold_amount' => 5.5,
            'description' => 'Test transaction',
            'currency' => 'IRR',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/customers/{$customer->id}/ledger", $ledgerData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ledger entry created successfully',
            ]);

        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id' => $customer->id,
            'transaction_type' => 'credit',
            'amount' => 1000,
            'description' => 'Test transaction',
        ]);
    }

    public function test_can_filter_customers_by_group(): void
    {
        $user = User::factory()->create();
        $group1 = CustomerGroup::factory()->create(['name' => 'Group 1']);
        $group2 = CustomerGroup::factory()->create(['name' => 'Group 2']);
        
        Customer::factory(3)->create(['customer_group_id' => $group1->id]);
        Customer::factory(2)->create(['customer_group_id' => $group2->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/customers?group_id={$group1->id}");

        $response->assertStatus(200);
        
        $customers = $response->json('data.data');
        $this->assertCount(3, $customers);
        
        foreach ($customers as $customer) {
            $this->assertEquals($group1->id, $customer['customer_group_id']);
        }
    }

    public function test_can_search_customers(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::factory()->create();
        
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'name' => 'احمد محمدی',
            'phone' => '09123456789',
        ]);
        
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'name' => 'علی احمدی',
            'phone' => '09987654321',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/customers?search=احمد');

        $response->assertStatus(200);
        
        $customers = $response->json('data.data');
        $this->assertCount(2, $customers);
    }
}
