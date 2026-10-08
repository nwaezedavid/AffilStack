<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'cta_click',
            'category' => AnalyticsEvent::CATEGORY_ENGAGEMENT,
            'path' => '/',
            'properties' => ['label' => 'Get started'],
            'created_at' => now(),
        ];
    }
}
