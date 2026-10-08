<?php

namespace Database\Factories;

use App\Models\AnalyticsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsProfile>
 */
class AnalyticsProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lifecycle_stage' => 'visitor',
            'engagement_score' => 10,
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDay(),
            'sessions_count' => 1,
            'pageviews_count' => 2,
            'computed_at' => now(),
        ];
    }
}
