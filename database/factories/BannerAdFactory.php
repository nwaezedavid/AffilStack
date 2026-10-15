<?php

namespace Database\Factories;

use App\Models\BannerAd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannerAd>
 */
class BannerAdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Spring promo',
            'headline' => 'Your affiliate business, one stack',
            'message' => 'Research offers, find buyers and launch AI campaigns in minutes.',
            'cta_label' => 'Claim the deal',
            'discount_badge' => '30% OFF',
            'discount_note' => 'Annual plans this week',
            'theme' => 'navy',
            'landing_path' => '/pricing',
            'weight' => 5,
            'is_active' => true,
        ];
    }

    public function off(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
