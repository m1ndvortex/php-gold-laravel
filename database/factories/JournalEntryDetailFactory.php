<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalEntryDetail>
 */
class JournalEntryDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 10, 5000);
        $isDebit = $this->faker->boolean();
        
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'description' => $this->faker->sentence(),
            'debit_amount' => $isDebit ? $amount : 0,
            'credit_amount' => $isDebit ? 0 : $amount,
            'cost_center' => $this->faker->optional()->randomElement(['SALES', 'ADMIN', 'PRODUCTION']),
            'additional_data' => $this->faker->optional()->randomElement([
                ['project_id' => $this->faker->numberBetween(1, 100)],
                ['department' => $this->faker->randomElement(['Finance', 'Operations', 'Marketing'])],
                null,
            ]),
        ];
    }

    /**
     * Create a debit entry
     */
    public function debit(float $amount = null): static
    {
        $amount = $amount ?? $this->faker->randomFloat(2, 10, 5000);
        
        return $this->state(fn (array $attributes) => [
            'debit_amount' => $amount,
            'credit_amount' => 0,
        ]);
    }

    /**
     * Create a credit entry
     */
    public function credit(float $amount = null): static
    {
        $amount = $amount ?? $this->faker->randomFloat(2, 10, 5000);
        
        return $this->state(fn (array $attributes) => [
            'debit_amount' => 0,
            'credit_amount' => $amount,
        ]);
    }

    /**
     * Create entry with specific cost center
     */
    public function withCostCenter(string $costCenter): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_center' => $costCenter,
        ]);
    }

    /**
     * Create entry for specific account
     */
    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }

    /**
     * Create entry for specific journal entry
     */
    public function forJournalEntry(JournalEntry $journalEntry): static
    {
        return $this->state(fn (array $attributes) => [
            'journal_entry_id' => $journalEntry->id,
        ]);
    }
}