<?php

namespace Database\Factories;

use App\Models\BrandLogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandLogo>
 */
class BrandLogoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'logo_path' => 'brand-logos/'.fake()->slug().'.png',
            'url' => fake()->url(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
