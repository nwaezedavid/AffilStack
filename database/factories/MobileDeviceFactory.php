<?php

namespace Database\Factories;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileDevice>
 */
class MobileDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['iPhone 16', 'Pixel 9', 'Galaxy S25']),
            'platform' => fake()->randomElement(['ios', 'android']),
            'app_version' => '1.0.0',
            'authenticated_at' => now(),
            'last_seen_at' => now(),
            'refresh_expires_at' => now()->addDays(60),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
