<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create customer groups first
        $this->call(CustomerGroupSeeder::class);

        // Get customer groups
        $groups = \App\Models\CustomerGroup::all();

        // Create sample customers
        \App\Models\Customer::factory(50)->create([
            'customer_group_id' => function () use ($groups) {
                return $groups->random()->id;
            },
        ]);

        // Create some customers with specific data for testing
        $specificCustomers = [
            [
                'name' => 'احمد محمدی',
                'phone' => '09123456789',
                'email' => 'ahmad@example.com',
                'customer_type' => 'individual',
                'birth_date' => now()->addDays(3), // Birthday in 3 days
                'credit_limit' => 5000,
                'current_balance' => 1000,
                'customer_group_id' => $groups->where('name', 'مشتریان VIP')->first()?->id,
            ],
            [
                'name' => 'فاطمه احمدی',
                'phone' => '09123456790',
                'email' => 'fateme@example.com',
                'customer_type' => 'individual',
                'birth_date' => now(), // Birthday today
                'credit_limit' => 3000,
                'current_balance' => 500,
                'customer_group_id' => $groups->where('name', 'مشتریان عادی')->first()?->id,
            ],
            [
                'name' => 'شرکت طلای پارس',
                'phone' => '02123456789',
                'email' => 'info@talaypars.com',
                'customer_type' => 'business',
                'tax_id' => '123456789',
                'credit_limit' => 50000,
                'current_balance' => 60000, // Exceeded credit limit
                'customer_group_id' => $groups->where('name', 'عمده فروشان')->first()?->id,
            ],
        ];

        foreach ($specificCustomers as $customerData) {
            \App\Models\Customer::create($customerData);
        }
    }
}
