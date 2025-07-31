<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit_price' => $this->faker->randomFloat(2, 10, 1000),
            'total_price' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['unit_price'];
            },
            'gold_weight_per_unit' => $this->faker->randomFloat(2, 0, 10),
            'total_gold_weight' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['gold_weight_per_unit'];
            },
            'manufacturing_fee' => $this->faker->randomFloat(2, 0, 100),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}