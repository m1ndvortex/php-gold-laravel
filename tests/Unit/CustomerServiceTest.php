<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class CustomerServiceTest extends TenantTestCase
{
    use RefreshDatabase;

    private CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerService = new CustomerService();
    }

    public function test_can_create_customer_with_opening_balance(): void
    {
        $group = CustomerGroup::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $customerData = [
            'name' => 'Test Customer',
            'phone' => '09123456789',
            'customer_group_id' => $group->id,
            'customer_type' => 'individual',
            'opening_balance' => 1000,
        ];

        $customer = $this->customerService->createCustomer($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('Test Customer', $customer->name);
        $this->assertEquals(1000, $customer->current_balance);
        
        // Check ledger entry was created
        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id' => $customer->id,
            'transaction_type' => 'credit',
            'amount' => 1000,
            'description' => 'Opening balance',
        ]);
    }

    public function test_can_create_ledger_entry(): void
    {
        $customer = Customer::factory()->create([
            'current_balance' => 500,
            'gold_balance' => 2.5,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $ledgerData = [
            'transaction_type' => 'credit',
            'amount' => 1000,
            'gold_amount' => 3.0,
            'description' => 'Test transaction',
            'currency' => 'IRR',
        ];

        $ledgerEntry = $this->customerService->createLedgerEntry($customer, $ledgerData);

        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id' => $customer->id,
            'transaction_type' => 'credit',
            'amount' => 1000,
            'gold_amount' => 3.0,
            'balance_after' => 1500,
            'gold_balance_after' => 5.5,
        ]);

        // Check customer balance was updated
        $customer->refresh();
        $this->assertEquals(1500, $customer->current_balance);
        $this->assertEquals(5.5, $customer->gold_balance);
    }

    public function test_can_get_upcoming_birthdays(): void
    {
        // Create customers with different birth dates
        Customer::factory()->create([
            'birth_date' => now()->addDays(3), // Within 7 days
        ]);
        
        Customer::factory()->create([
            'birth_date' => now()->addDays(10), // Outside 7 days
        ]);
        
        Customer::factory()->create([
            'birth_date' => now()->subYear()->addDays(5), // Birthday in 5 days (next year)
        ]);

        $upcomingBirthdays = $this->customerService->getUpcomingBirthdays(7);

        $this->assertCount(2, $upcomingBirthdays);
    }

    public function test_can_get_todays_birthdays(): void
    {
        // Create customer with birthday today
        Customer::factory()->create([
            'birth_date' => now()->subYears(30), // 30 years ago, same date
        ]);
        
        // Create customer with birthday tomorrow
        Customer::factory()->create([
            'birth_date' => now()->addDay()->subYears(25),
        ]);

        $todaysBirthdays = $this->customerService->getTodaysBirthdays();

        $this->assertCount(1, $todaysBirthdays);
    }

    public function test_can_get_customer_statistics(): void
    {
        $group = CustomerGroup::factory()->create();
        
        // Create customers (ensure they are active)
        Customer::factory(5)->create([
            'customer_group_id' => $group->id,
            'created_at' => now(),
            'is_active' => true,
        ]);
        
        // Create customer with exceeded credit limit
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'credit_limit' => 1000,
            'current_balance' => 1500,
            'is_active' => true,
        ]);
        
        // Create customer with upcoming birthday
        Customer::factory()->create([
            'customer_group_id' => $group->id,
            'birth_date' => now()->addDays(3),
            'is_active' => true,
        ]);

        $statistics = $this->customerService->getCustomerStatistics();

        $this->assertArrayHasKey('total_customers', $statistics);
        $this->assertArrayHasKey('new_this_month', $statistics);
        $this->assertArrayHasKey('credit_limit_exceeded', $statistics);
        $this->assertArrayHasKey('upcoming_birthdays', $statistics);
        $this->assertArrayHasKey('top_customers', $statistics);
        
        $this->assertEquals(7, $statistics['total_customers']);
        $this->assertEquals(1, $statistics['credit_limit_exceeded']);
        $this->assertEquals(1, $statistics['upcoming_birthdays']);
    }

    public function test_can_import_customers(): void
    {
        $group = CustomerGroup::factory()->create();
        
        $customersData = [
            [
                'name' => 'Customer 1',
                'phone' => '09123456789',
                'email' => 'customer1@example.com',
                'customer_type' => 'individual',
                'customer_group_id' => $group->id,
            ],
            [
                'name' => 'Customer 2',
                'phone' => '09123456790',
                'email' => 'customer2@example.com',
                'customer_type' => 'business',
                'customer_group_id' => $group->id,
            ],
            [
                'name' => '', // Invalid - missing name
                'phone' => '09123456791',
            ],
        ];

        $result = $this->customerService->importCustomers($customersData);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(1, count($result['errors']));
        $this->assertEquals(3, $result['total']);
        
        $this->assertDatabaseHas('customers', [
            'name' => 'Customer 1',
            'phone' => '09123456789',
        ]);
        
        $this->assertDatabaseHas('customers', [
            'name' => 'Customer 2',
            'phone' => '09123456790',
        ]);
    }

    public function test_delete_customer_with_invoices_marks_inactive(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        
        // Create a user and role for the invoice
        $role = \App\Models\Role::factory()->create();
        $user = \App\Models\User::factory()->create(['role_id' => $role->id]);
        
        // Create an invoice for the customer
        \App\Models\Invoice::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
        ]);

        $result = $this->customerService->deleteCustomer($customer);

        $this->assertTrue($result);
        
        $customer->refresh();
        $this->assertFalse($customer->is_active);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_customer_without_invoices_deletes_record(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->customerService->deleteCustomer($customer);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }
}
