<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => $this->faker->randomElement(['', 'pricing', 'about', 'help', 'dashboard']),
            'source' => $this->faker->randomElement(['Direct', 'Organic Search', 'Social', 'Referral', 'Internal']),
            'referrer_host' => null,
            'user_id' => null,
            'session_id' => $this->faker->uuid(),
            'duration_seconds' => $this->faker->numberBetween(5, 300),
            'created_at' => now(),
        ];
    }
}
