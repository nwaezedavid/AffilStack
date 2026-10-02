<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Chief Executive Officer (CEO)', 'Chief Financial Officer (CFO)', 'Chief Operating Officer (COO)']),
            'bio' => fake()->sentence(16),
            'photo_path' => null,
            'linkedin_url' => null,
            'x_url' => null,
            'website_url' => null,
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }
}
