<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = $this->faker->randomFloat(2, 100, 10000);
        
        return [
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => $this->faker->sentence(),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'reference_type' => $this->faker->optional()->randomElement(['invoice', 'payment', 'adjustment']),
            'reference_id' => $this->faker->optional()->numberBetween(1, 1000),
            'total_debit' => $totalAmount,
            'total_credit' => $totalAmount,
            'status' => $this->faker->randomElement(['draft', 'posted']),
            'is_recurring' => false,
            'is_system_generated' => $this->faker->boolean(30),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create a posted journal entry
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_by' => User::factory(),
            'posted_at' => $this->faker->dateTimeBetween($attributes['entry_date'] ?? '-1 year', 'now'),
        ]);
    }

    /**
     * Create a draft journal entry
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'posted_by' => null,
            'posted_at' => null,
        ]);
    }

    /**
     * Create a reversed journal entry
     */
    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reversed',
        ]);
    }

    /**
     * Create a recurring journal entry
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurring_pattern' => $this->faker->randomElement(['monthly', 'quarterly', 'yearly']),
            'next_recurring_date' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
        ]);
    }

    /**
     * Create a system generated journal entry
     */
    public function systemGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system_generated' => true,
        ]);
    }

    /**
     * Create a journal entry with specific reference
     */
    public function withReference(string $type, int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $type,
            'reference_id' => $id,
            'reference' => strtoupper($type) . '-' . $id,
        ]);
    }
}