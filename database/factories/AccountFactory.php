<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']);
        
        return [
            'code' => $this->faker->unique()->numerify('####'),
            'name' => $this->faker->words(3, true),
            'name_en' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'description_en' => $this->faker->sentence(),
            'type' => $type,
            'subtype' => $this->getSubtypeForType($type),
            'is_active' => true,
            'is_system' => false,
            'opening_balance' => $this->faker->randomFloat(2, 0, 10000),
            'current_balance' => $this->faker->randomFloat(2, 0, 10000),
            'normal_balance' => $this->getNormalBalanceForType($type),
            'level' => 1,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Get appropriate subtype for account type
     */
    private function getSubtypeForType(string $type): ?string
    {
        return match ($type) {
            'asset' => $this->faker->randomElement(['current_asset', 'fixed_asset', 'other_asset']),
            'liability' => $this->faker->randomElement(['current_liability', 'long_term_liability']),
            'equity' => $this->faker->randomElement(['owner_equity', 'retained_earnings']),
            'revenue' => $this->faker->randomElement(['operating_revenue', 'other_revenue']),
            'expense' => $this->faker->randomElement(['operating_expense', 'other_expense']),
            default => null,
        };
    }

    /**
     * Get normal balance for account type
     */
    private function getNormalBalanceForType(string $type): string
    {
        return match ($type) {
            'asset', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            default => 'debit',
        };
    }

    /**
     * Create an asset account
     */
    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'asset',
            'subtype' => $this->faker->randomElement(['current_asset', 'fixed_asset', 'other_asset']),
            'normal_balance' => 'debit',
        ]);
    }

    /**
     * Create a liability account
     */
    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'liability',
            'subtype' => $this->faker->randomElement(['current_liability', 'long_term_liability']),
            'normal_balance' => 'credit',
        ]);
    }

    /**
     * Create an equity account
     */
    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'equity',
            'subtype' => $this->faker->randomElement(['owner_equity', 'retained_earnings']),
            'normal_balance' => 'credit',
        ]);
    }

    /**
     * Create a revenue account
     */
    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'revenue',
            'subtype' => $this->faker->randomElement(['operating_revenue', 'other_revenue']),
            'normal_balance' => 'credit',
        ]);
    }

    /**
     * Create an expense account
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'subtype' => $this->faker->randomElement(['operating_expense', 'other_expense']),
            'normal_balance' => 'debit',
        ]);
    }

    /**
     * Create a system account
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Create an inactive account
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}