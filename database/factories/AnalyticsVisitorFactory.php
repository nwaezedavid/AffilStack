<?php

namespace Database\Factories;

use App\Models\AnalyticsVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AnalyticsVisitor>
 */
class AnalyticsVisitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seen = now()->subDays($this->faker->numberBetween(0, 20));
        $channel = $this->faker->randomElement(['Direct', 'Organic Search', 'Social', 'Paid', 'Referral']);

        return [
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => $seen,
            'last_seen_at' => $seen,
            'ft_channel' => $channel,
            'ft_source' => $channel === 'Direct' ? '(direct)' : $this->faker->randomElement(['google', 'facebook', 'tiktok']),
            'ft_landing_path' => '/',
            'ft_at' => $seen,
            'lt_channel' => $channel,
            'lt_at' => $seen,
            'device_type' => $this->faker->randomElement(['desktop', 'mobile']),
            'browser' => 'Chrome',
            'os' => 'Android',
            'language' => 'en-NG',
            'country' => 'NG',
            'sessions_count' => 1,
            'pageviews_count' => 1,
        ];
    }
}
