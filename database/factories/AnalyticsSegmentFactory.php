<?php

namespace Database\Factories;

use App\Models\AnalyticsSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsSegment>
 */
class AnalyticsSegmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'purpose' => 'target',
            'rules' => [['field' => 'engagement_score', 'operator' => 'gte', 'value' => 50]],
        ];
    }
}
