<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['VIP', 'Wholesale', 'Regular', 'New Customer', 'Premium']),
            'name_en' => $this->faker->optional()->randomElement(['VIP', 'Wholesale', 'Regular', 'New Customer', 'Premium']),
            'description' => $this->faker->optional()->sentence(),
            'description_en' => $this->faker->optional()->sentence(),
            'discount_percentage' => $this->faker->randomFloat(2, 0, 20),
            'credit_limit_multiplier' => $this->faker->randomFloat(2, 0.5, 3.0),
            'is_active' => $this->faker->boolean(90),
            'settings' => $this->faker->optional()->randomElement([
                null,
                ['special_pricing' => true],
                ['priority_support' => true, 'extended_warranty' => true],
            ]),
        ];
    }
}
