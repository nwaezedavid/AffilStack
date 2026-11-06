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

    /**
     * A phone that allowed notifications and sent its Expo push token.
     */
    public function withPush(?string $token = null): static
    {
        return $this->state(fn (): array => [
            'push_provider' => 'expo',
            'push_token' => $token ?? 'ExponentPushToken['.fake()->unique()->bothify('????????????????????').']',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
