<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $goldWeight = $this->faker->randomFloat(3, 0, 50);
        $stoneWeight = $this->faker->randomFloat(3, 0, 10);
        $goldPrice = $this->faker->numberBetween(2000000, 3000000);
        $manufacturingCost = $this->faker->numberBetween(100000, 2000000);
        $profitMargin = $this->faker->randomFloat(2, 10, 50);
        
        // Calculate unit price based on gold weight and costs
        $baseCost = ($goldWeight * $goldPrice) + $manufacturingCost;
        $unitPrice = $baseCost * (1 + ($profitMargin / 100));

        return [
            'name' => $this->faker->randomElement([
                'Gold Ring', 'Gold Necklace', 'Gold Bracelet', 'Gold Earrings',
                'Diamond Ring', 'Pearl Necklace', 'Silver Chain', 'Gold Coin',
                'Wedding Ring', 'Engagement Ring', 'Gold Pendant', 'Gold Watch'
            ]),
            'name_en' => $this->faker->optional()->randomElement([
                'Gold Ring', 'Gold Necklace', 'Gold Bracelet', 'Gold Earrings',
                'Diamond Ring', 'Pearl Necklace', 'Silver Chain', 'Gold Coin'
            ]),
            'sku' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{4}'),
            'barcode' => $this->faker->optional()->ean13(),
            'category_id' => ProductCategory::factory(),
            'type' => $this->faker->randomElement(['finished_jewelry', 'raw_gold', 'coins', 'stones', 'other']),
            'description' => $this->faker->optional()->paragraph(),
            'description_en' => $this->faker->optional()->paragraph(),
            'gold_weight' => $goldWeight,
            'stone_weight' => $stoneWeight,
            'total_weight' => $goldWeight + $stoneWeight,
            'manufacturing_cost' => $manufacturingCost,
            'current_stock' => $this->faker->numberBetween(0, 100),
            'minimum_stock' => $this->faker->numberBetween(1, 10),
            'maximum_stock' => $this->faker->optional()->numberBetween(50, 200),
            'unit_price' => $unitPrice,
            'selling_price' => $unitPrice * $this->faker->randomFloat(2, 1.1, 1.5),
            'unit_of_measure' => $this->faker->randomElement(['piece', 'gram', 'carat']),
            'is_active' => $this->faker->boolean(95), // 95% chance of being active
            'track_stock' => $this->faker->boolean(80), // 80% chance of tracking stock
            'has_bom' => $this->faker->boolean(20), // 20% chance of having BOM
            'images' => $this->faker->optional()->randomElement([
                null,
                ['image1.jpg', 'image2.jpg'],
                ['product.png'],
            ]),
            'specifications' => $this->faker->optional()->randomElement([
                null,
                ['purity' => '18K', 'origin' => 'Iran'],
                ['stone_type' => 'Diamond', 'clarity' => 'VS1'],
                ['dimensions' => '2x3cm', 'weight_unit' => 'gram'],
            ]),
            'tags' => $this->faker->optional()->randomElements(['Popular', 'New', 'Sale', 'Premium'], $this->faker->numberBetween(0, 2)),
            'location' => $this->faker->optional()->randomElement(['A1', 'B2', 'C3', 'Storage Room 1']),
        ];
    }

    /**
     * Indicate that the product is finished jewelry
     */
    public function finishedJewelry(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'finished_jewelry',
            'name' => $this->faker->randomElement([
                'Gold Ring', 'Gold Necklace', 'Gold Bracelet', 'Gold Earrings',
                'Diamond Ring', 'Wedding Ring', 'Engagement Ring'
            ]),
            'gold_weight' => $this->faker->randomFloat(3, 1, 20),
            'stone_weight' => $this->faker->randomFloat(3, 0, 5),
        ]);
    }

    /**
     * Indicate that the product is raw gold
     */
    public function rawGold(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'raw_gold',
            'name' => $this->faker->randomElement(['Gold Bar', 'Gold Sheet', 'Gold Wire', 'Gold Granules']),
            'gold_weight' => $this->faker->randomFloat(3, 10, 100),
            'stone_weight' => 0,
            'manufacturing_cost' => 0,
        ]);
    }

    /**
     * Indicate that the product is a coin
     */
    public function coin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'coins',
            'name' => $this->faker->randomElement(['Gold Coin', 'Silver Coin', 'Commemorative Coin']),
            'gold_weight' => $this->faker->randomFloat(3, 5, 30),
            'stone_weight' => 0,
        ]);
    }

    /**
     * Indicate that the product is stones
     */
    public function stones(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'stones',
            'name' => $this->faker->randomElement(['Diamond', 'Ruby', 'Emerald', 'Sapphire', 'Pearl']),
            'gold_weight' => 0,
            'stone_weight' => $this->faker->randomFloat(3, 0.1, 5),
            'manufacturing_cost' => 0,
        ]);
    }

    /**
     * Indicate that the product is out of stock
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_stock' => 0,
        ]);
    }

    /**
     * Indicate that the product is low stock
     */
    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $minStock = $attributes['minimum_stock'] ?? 5;
            return [
                'current_stock' => $this->faker->numberBetween(1, $minStock - 1),
            ];
        });
    }

    /**
     * Indicate that the product is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
