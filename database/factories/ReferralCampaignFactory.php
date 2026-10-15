<?php

namespace Database\Factories;

use App\Models\ReferralCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCampaign>
 */
class ReferralCampaignFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'user_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'channel' => 'website',
            'landing_path' => '/pricing',
        ];
    }
}
