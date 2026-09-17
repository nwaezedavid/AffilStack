<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralEvent>
 */
class ReferralEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referral_id' => Referral::factory(),
            'event_type' => 'first_payment',
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'pending',
            'occurred_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved']);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paid']);
    }
}
