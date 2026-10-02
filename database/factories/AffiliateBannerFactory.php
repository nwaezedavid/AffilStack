<?php

namespace Database\Factories;

use App\Models\AffiliateBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateBanner>
 */
class AffiliateBannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'size' => '300x250',
            'image_path' => 'affiliate-banners/'.fake()->uuid().'.png',
            'alt_text' => 'Try AffilStack',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
