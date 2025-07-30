<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['admin', 'manager', 'user', 'viewer']),
            'display_name' => fake()->jobTitle(),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Create an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Full system access',
        ]);
    }

    /**
     * Create a user role.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'user',
            'display_name' => 'User',
            'description' => 'Standard user access',
        ]);
    }
}