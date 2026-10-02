<?php

namespace Database\Factories;

use App\Models\AffiliateSwipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateSwipe>
 */
class AffiliateSwipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'email',
            'title' => fake()->sentence(3),
            'subject' => fake()->sentence(),
            'body' => "Hi {first_name},\n\nTake a look: {affiliate_link}\n\n{your_name}",
            'platform' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function social(string $platform = 'x'): static
    {
        return $this->state(fn () => ['type' => 'social', 'subject' => null, 'platform' => $platform, 'body' => 'Check out AffilStack: {affiliate_link}']);
    }
}
