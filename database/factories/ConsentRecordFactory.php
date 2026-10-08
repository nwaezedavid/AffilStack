<?php

namespace Database\Factories;

use App\Models\ConsentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consent_id' => (string) Str::uuid(),
            'analytics' => true,
            'marketing' => true,
            'action' => 'accept_all',
            'source' => 'banner',
            'policy_version' => (string) config('analytics.policy_version'),
            'ip_hash' => hash('sha256', $this->faker->ipv4()),
            'browser' => 'Chrome',
            'device_type' => 'desktop',
            'page_path' => '/',
            'created_at' => now(),
        ];
    }

    public function rejected(): static
    {
        return $this->state(['analytics' => false, 'marketing' => false, 'action' => 'reject_all']);
    }

    public function analyticsOnly(): static
    {
        return $this->state(['analytics' => true, 'marketing' => false, 'action' => 'custom']);
    }
}
