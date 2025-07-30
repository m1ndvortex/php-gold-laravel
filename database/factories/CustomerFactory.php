<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->optional()->address(),
            'tax_id' => $this->faker->optional()->numerify('##########'),
            'customer_group_id' => CustomerGroup::factory(),
            'credit_limit' => $this->faker->numberBetween(0, 100000000),
            'current_balance' => $this->faker->numberBetween(-10000000, 50000000),
            'birth_date' => $this->faker->optional()->date(),
            'notes' => $this->faker->optional()->paragraph(),
            'tags' => $this->faker->optional()->randomElements(['VIP', 'Wholesale', 'Regular', 'New'], $this->faker->numberBetween(0, 2)),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the customer is a VIP
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['VIP'],
            'credit_limit' => $this->faker->numberBetween(50000000, 200000000),
        ]);
    }

    /**
     * Indicate that the customer is a wholesaler
     */
    public function wholesale(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['Wholesale'],
            'credit_limit' => $this->faker->numberBetween(20000000, 100000000),
        ]);
    }

    /**
     * Indicate that the customer is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
