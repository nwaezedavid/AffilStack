<?php

namespace Database\Factories;

use App\Models\AgentTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTask>
 */
class AgentTaskFactory extends Factory
{
    protected $model = AgentTask::class;

    public function definition(): array
    {
        return [
            'agent' => AgentTask::AGENT_SECURITY,
            'type' => 'security_fix',
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'payload' => [],
            'risk_level' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => AgentTask::STATUS_PENDING,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => AgentTask::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
            'scheduled_until' => now()->addDay()->addHour(),
            'approved_at' => now(),
        ]);
    }
}
