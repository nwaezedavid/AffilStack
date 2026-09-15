<?php

namespace Database\Factories;

use App\Models\SecurityFinding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SecurityFinding>
 */
class SecurityFindingFactory extends Factory
{
    protected $model = SecurityFinding::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'fingerprint' => sha1('config|'.$title.'|'.Str::random(8)),
            'category' => 'config',
            'title' => $title,
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'evidence' => ['note' => fake()->sentence()],
            'ai_summary' => fake()->paragraph(),
            'ai_suggested_fix' => fake()->paragraph(),
            'fix_action' => null,
            'status' => SecurityFinding::STATUS_OPEN,
            'detected_at' => now(),
        ];
    }
}
