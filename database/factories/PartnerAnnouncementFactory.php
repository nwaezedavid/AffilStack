<?php

namespace Database\Factories;

use App\Models\PartnerAnnouncement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerAnnouncement>
 */
class PartnerAnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_pinned' => false,
            'published_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }
}
