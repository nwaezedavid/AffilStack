<?php

namespace Database\Factories;

use App\Models\CannedReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedReply>
 */
class CannedReplyFactory extends Factory
{
    protected $model = CannedReply::class;

    public function definition(): array
    {
        return [
            'title' => ucfirst($this->faker->words(3, true)),
            'body' => $this->faker->paragraph(),
            'status' => CannedReply::STATUS_ACTIVE,
            'source' => CannedReply::SOURCE_MANUAL,
            'usage_count' => 0,
            'last_used_at' => null,
            'ai_rationale' => null,
        ];
    }

    public function suggested(): static
    {
        return $this->state(fn () => [
            'status' => CannedReply::STATUS_SUGGESTED,
            'source' => CannedReply::SOURCE_AI_SUGGESTED,
            'ai_rationale' => $this->faker->sentence(),
        ]);
    }
}
