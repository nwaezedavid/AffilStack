<?php

namespace Database\Factories;

use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsSession>
 */
class AnalyticsSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDays($this->faker->numberBetween(0, 20));

        return [
            'visitor_id' => AnalyticsVisitor::factory(),
            'started_at' => $start,
            'last_activity_at' => $start->copy()->addMinutes(3),
            'duration_seconds' => 180,
            'engaged_seconds' => 90,
            'pageviews' => 3,
            'events' => 1,
            'landing_path' => '/',
            'exit_path' => 'pricing',
            'channel' => 'Organic Search',
            'source' => 'google',
            'medium' => 'organic',
            'device_type' => 'mobile',
            'browser' => 'Chrome',
            'os' => 'Android',
            'language' => 'en-NG',
            'country' => 'NG',
        ];
    }
}
