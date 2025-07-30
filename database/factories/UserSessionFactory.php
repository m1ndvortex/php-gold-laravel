<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSession>
 */
class UserSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => Str::random(40),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'device_name' => fake()->randomElement(['iPhone', 'Samsung Galaxy', 'MacBook Pro', 'Windows PC']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'platform' => fake()->randomElement(['Windows', 'macOS', 'iOS', 'Android', 'Linux']),
            'location' => [
                'city' => fake()->city(),
                'country' => fake()->country(),
                'country_code' => fake()->countryCode(),
            ],
            'is_current' => false,
            'last_activity' => now(),
            'logged_out_at' => null,
        ];
    }

    /**
     * Indicate that this is the current session.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
        ]);
    }

    /**
     * Indicate that this session is logged out.
     */
    public function loggedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'logged_out_at' => now(),
            'is_current' => false,
        ]);
    }

    /**
     * Indicate that this session is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity' => now()->subHours(3),
        ]);
    }

    /**
     * Set specific device type.
     */
    public function deviceType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => $type,
        ]);
    }

    /**
     * Set specific IP address.
     */
    public function ipAddress(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
        ]);
    }
}