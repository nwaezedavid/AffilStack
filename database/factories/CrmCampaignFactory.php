<?php

namespace Database\Factories;

use App\Models\CrmCampaign;
use App\Models\CrmList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmCampaign>
 */
class CrmCampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'crm_list_id' => fn (array $attributes) => CrmList::factory()->create(['user_id' => $attributes['user_id']])->id,
            'offer_id' => null,
            'name' => fake()->words(3, true),
            'subject' => 'Quick idea for {company}',
            'body' => "Hi {first_name},\n\nI noticed {company} in {city}.\n\nTake a look: {affiliate_link}\n\nCheers",
            'status' => 'draft',
            'autopilot' => false,
            'personalize_opening' => false,
            'follow_up_steps' => [],
        ];
    }

    public function launched(): static
    {
        return $this->state(fn () => ['status' => 'sent', 'approved_at' => now()]);
    }
}
