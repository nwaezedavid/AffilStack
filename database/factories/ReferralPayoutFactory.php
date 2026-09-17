<?php

namespace Database\Factories;

use App\Models\ReferralPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralPayout>
 */
class ReferralPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount_cents' => 10000,
            'currency' => 'USD',
            'status' => 'requested',
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => fake()->safeEmail()],
            'requested_at' => now(),
        ];
    }
}
