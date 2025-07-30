<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Rings', 'Necklaces', 'Bracelets', 'Earrings', 
            'Raw Gold', 'Coins', 'Stones', 'Watches'
        ]);
        
        return [
            'name' => $name,
            'name_en' => $this->faker->optional()->word(),
            'description' => $this->faker->optional()->sentence(),
            'description_en' => $this->faker->optional()->sentence(),
            'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'type' => $this->faker->randomElement(['raw_gold', 'finished_jewelry', 'coins', 'stones', 'other']),
            'parent_id' => null, // Can be overridden for subcategories
            'is_active' => $this->faker->boolean(95),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'settings' => $this->faker->optional()->randomElement([
                null,
                ['display_weight' => true],
                ['require_certification' => true, 'min_purity' => '14K'],
            ]),
        ];
    }

    /**
     * Indicate that the category is a subcategory
     */
    public function subcategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => ProductCategory::factory(),
        ]);
    }
}
