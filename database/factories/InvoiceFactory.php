<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(1000000, 50000000);
        $discountAmount = $this->faker->numberBetween(0, $subtotal * 0.1);
        $taxableAmount = $subtotal - $discountAmount;
        $vatPercentage = $this->faker->randomFloat(2, 0, 15);
        $vatAmount = $taxableAmount * ($vatPercentage / 100);
        $totalAmount = $taxableAmount + $vatAmount;
        $paidAmount = $this->faker->numberBetween(0, $totalAmount);
        $balanceDue = $totalAmount - $paidAmount;

        return [
            'invoice_number' => $this->generateInvoiceNumber(),
            'customer_id' => Customer::factory(),
            'type' => $this->faker->randomElement(['sale', 'purchase', 'trade']),
            'status' => $this->faker->randomElement(['draft', 'pending', 'paid', 'partial', 'cancelled', 'overdue']),
            'invoice_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'subtotal' => $subtotal,
            'tax_amount' => $vatAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'total_gold_weight' => $this->faker->randomFloat(3, 0, 100),
            'gold_price_per_gram' => $this->faker->numberBetween(2000000, 3000000),
            'manufacturing_fee' => $this->faker->numberBetween(100000, 1000000),
            'profit_margin_percentage' => $this->faker->randomFloat(2, 10, 50),
            'vat_percentage' => $vatPercentage,
            'currency' => 'IRR',
            'notes' => $this->faker->optional()->sentence(),
            'terms_conditions' => $this->faker->optional()->paragraph(),
            'custom_fields' => $this->faker->optional()->randomElement([
                null,
                ['special_instructions' => $this->faker->sentence()],
                ['delivery_date' => $this->faker->date()],
            ]),
            'is_recurring' => $this->faker->boolean(10), // 10% chance of being recurring
            'recurring_pattern' => $this->faker->optional()->randomElement(['monthly', 'quarterly', 'yearly']),
            'next_recurring_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Generate a unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = $this->faker->randomElement(['INV', 'PUR', 'TRD']);
        $year = date('Y');
        $month = date('m');
        $number = $this->faker->unique()->numberBetween(1000, 9999);
        
        return "{$prefix}-{$year}{$month}-{$number}";
    }

    /**
     * Indicate that the invoice is a sale
     */
    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sale',
            'invoice_number' => 'INV-' . date('Ym') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }

    /**
     * Indicate that the invoice is a purchase
     */
    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'purchase',
            'invoice_number' => 'PUR-' . date('Ym') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }

    /**
     * Indicate that the invoice is a trade
     */
    public function trade(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'trade',
            'invoice_number' => 'TRD-' . date('Ym') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }

    /**
     * Indicate that the invoice is paid
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'paid_amount' => $attributes['total_amount'],
                'balance_due' => 0,
            ];
        });
    }

    /**
     * Indicate that the invoice is pending
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'paid_amount' => 0,
                'balance_due' => $attributes['total_amount'],
            ];
        });
    }

    /**
     * Indicate that the invoice is partially paid
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $partialAmount = $attributes['total_amount'] * $this->faker->randomFloat(2, 0.1, 0.9);
            return [
                'status' => 'partial',
                'paid_amount' => $partialAmount,
                'balance_due' => $attributes['total_amount'] - $partialAmount,
            ];
        });
    }

    /**
     * Indicate that the invoice is overdue
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'overdue',
                'due_date' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
                'paid_amount' => 0,
                'balance_due' => $attributes['total_amount'],
            ];
        });
    }

    /**
     * Indicate that the invoice is recurring
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurring_pattern' => $this->faker->randomElement(['monthly', 'quarterly', 'yearly']),
            'next_recurring_date' => $this->faker->dateTimeBetween('now', '+1 year'),
        ]);
    }
}
