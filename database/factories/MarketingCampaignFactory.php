<?php

namespace Database\Factories;

use App\Models\MarketingCampaign;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingCampaign>
 */
class MarketingCampaignFactory extends Factory
{
    protected $model = MarketingCampaign::class;

    public function definition(): array
    {
        return [
            // Offer has no factory of its own project-wide — every existing
            // test creates one directly via Offer::create(), so this does
            // the same rather than introducing the project's first one.
            'offer_id' => fn () => Offer::create([
                'user_id' => User::factory()->create()->id,
                'product_name' => $this->faker->words(2, true),
                'product_url' => $this->faker->url(),
                'affiliate_network' => 'ShareASale',
                'status' => 'ready',
            ])->id,
            'created_by_id' => User::factory(),
            'title' => $this->faker->catchPhrase(),
            'goal' => 'conversions',
            'status' => MarketingCampaign::STATUS_DRAFT,
            'brief' => [
                'ad_copy_variants' => [
                    ['headline' => 'Test headline', 'primary_text' => 'Test primary text.', 'description' => 'Test description.'],
                ],
                'targeting' => ['age_range' => '25-45', 'interests' => ['affiliate marketing'], 'geos' => ['US']],
                'budget_suggestion' => ['daily_min' => 10, 'daily_max' => 30, 'currency' => 'USD'],
                'image_prompts' => ['A clean product photo on a gradient background.'],
            ],
            'image_urls' => [],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => MarketingCampaign::STATUS_APPROVED,
            'approved_by_id' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => MarketingCampaign::STATUS_RUNNING,
            'approved_by_id' => User::factory(),
            'approved_at' => now()->subDay(),
            'meta_campaign_ref' => 'act_test_'.$this->faker->randomNumber(6),
        ]);
    }
}
