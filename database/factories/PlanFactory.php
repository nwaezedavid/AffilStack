<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price_monthly_cents' => 9700,
            'price_yearly_cents' => 97000,
            'currency' => 'USD',
            'credits_per_month' => 600,
            'active_products_limit' => 1,
            'contact_limit' => 500,
            'team_seats' => 1,
            'seat_mode' => 'isolated',
            'channels' => ['research', 'blog', 'linkedin'],
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function sharedTeamPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_mode' => 'shared',
            'team_seats' => 5,
        ]);
    }
}
