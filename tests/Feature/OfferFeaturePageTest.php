<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\OfferFeatures;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The dedicated per-feature product pages (route offers.feature): routing,
 * plan gating, team-seat access, generation redirects landing back on the
 * right page, and the history poll.
 */
class OfferFeaturePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    /**
     * @param  array<int, string>  $channels
     */
    protected function owner(array $channels = ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok']): User
    {
        $plan = Plan::factory()->create(['channels' => $channels, 'seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = User::factory()->create(['credits_balance' => 1000]);
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);

        return $owner->fresh();
    }

    protected function offer(User $owner): Offer
    {
        return Offer::create([
            'user_id' => $owner->id, 'product_name' => 'Acme Widget', 'product_url' => 'https://example.com',
            'affiliate_network' => 'Impact.com', 'status' => 'ready', 'recommended_channel' => 'youtube',
            'recommended_angle' => 'Save an hour a day.', 'research_data' => ['pain_points' => ['Wastes time']],
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function featureKeys(): array
    {
        return collect(array_keys(OfferFeatures::all()))->mapWithKeys(fn (string $key) => [$key => [$key]])->all();
    }

    #[DataProvider('featureKeys')]
    public function test_every_feature_page_renders_for_the_owner(string $key): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);

        $this->actingAs($owner)->get(route('offers.feature', [$offer, $key]))
            ->assertOk()
            ->assertSee(OfferFeatures::get($key)['label'])
            ->assertSee('How '.OfferFeatures::get($key)['label'].' works', false);
    }

    public function test_the_product_hub_links_every_feature_tile_and_badges_the_recommended_one(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);

        $response = $this->actingAs($owner)->get(route('offers.show', $offer))->assertOk();

        foreach (array_keys(OfferFeatures::all()) as $key) {
            $response->assertSee(route('offers.feature', [$offer, $key]), false);
        }
        $response->assertSee('Start with YouTube', false);
    }

    public function test_an_unknown_feature_is_a_404(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('offers.feature', [$this->offer($owner), 'fax-machine']))->assertNotFound();
    }

    public function test_another_users_product_is_forbidden(): void
    {
        $offer = $this->offer($this->owner());

        $this->actingAs($this->owner())->get(route('offers.feature', [$offer, 'blog']))->assertForbidden();
    }

    public function test_a_plan_without_the_channel_shows_the_upgrade_path_instead_of_the_form(): void
    {
        $owner = $this->owner(['research', 'blog', 'linkedin']);
        $offer = $this->offer($owner);

        $this->actingAs($owner)->get(route('offers.feature', [$offer, 'youtube']))
            ->assertOk()
            ->assertSee("isn't included in your current plan", false)
            ->assertSee(route('billing.index'), false)
            ->assertDontSee(route('offers.youtube.script', $offer), false);

        $this->actingAs($owner)->get(route('offers.show', $offer))->assertSee('Upgrade to unlock');
    }

    public function test_an_isolated_seat_can_open_feature_pages_for_its_own_offer_only(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);
        $otherOffer = $this->offer($owner);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('offers.feature', [$offer, 'blog']))
            ->assertOk()
            ->assertSee(route('offers.blog.store', $offer), false);

        $this->actingAs($seat)->get(route('offers.feature', [$otherOffer, 'blog']))->assertForbidden();
    }

    public function test_owner_only_features_are_explained_not_offered_to_a_seat(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('offers.feature', [$offer, 'nurture']))
            ->assertOk()
            ->assertSee('Only the account owner can use Email nurture')
            ->assertDontSee(route('offers.nurture.generate', $offer), false);

        $this->actingAs($seat)->get(route('offers.feature', [$offer, 'maps']))
            ->assertOk()
            ->assertSee('Only the account owner can use Local leads')
            ->assertDontSee('Your prospects for this product');
    }

    public function test_generating_content_returns_to_that_features_page(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $offer = $this->offer($owner);

        $this->actingAs($owner)->post(route('offers.blog.store', $offer), ['target_keyword' => 'acme review'])
            ->assertRedirect(route('offers.feature', [$offer, 'blog']));
        $this->actingAs($owner)->post(route('offers.x.thread', $offer))
            ->assertRedirect(route('offers.feature', [$offer, 'x']));
        $this->actingAs($owner)->post(route('offers.competitor.scan', $offer))
            ->assertRedirect(route('offers.feature', [$offer, 'competitor']));
    }

    public function test_the_history_poll_returns_pending_state_and_rendered_content(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);
        $offer->generations()->create(['user_id' => $owner->id, 'module' => 'blog_article', 'status' => 'queued', 'input' => []]);

        $this->actingAs($owner)->getJson(route('offers.feature', [$offer, 'blog', 'fragment' => 'history']))
            ->assertOk()
            ->assertJson(['pending' => true])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Our AI is writing this now'));

        $offer->generations()->update([
            'status' => 'completed', 'output' => 'Body', 'output_meta' => ['title' => 'Acme Widget: the honest review', 'meta_description' => 'Meta'],
        ]);

        $this->actingAs($owner)->getJson(route('offers.feature', [$offer, 'blog', 'fragment' => 'history']))
            ->assertOk()
            ->assertJson(['pending' => false])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Acme Widget: the honest review'));
    }

    public function test_a_finished_piece_shows_a_what_to_do_next_nudge(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);
        $offer->generations()->create([
            'user_id' => $owner->id, 'module' => 'blog_article', 'status' => 'completed',
            'output' => 'Body', 'output_meta' => ['title' => 'Review'],
        ]);

        $this->actingAs($owner)->get(route('offers.feature', [$offer, 'blog']))
            ->assertOk()
            ->assertSee('What to do next')
            ->assertSee('Publish the article on your blog or Medium');
    }

    public function test_a_generation_job_notification_links_to_its_feature_page(): void
    {
        $owner = $this->owner();
        $offer = $this->offer($owner);
        $generation = $offer->generations()->create(['user_id' => $owner->id, 'module' => 'pinterest_pin', 'status' => 'completed']);

        $this->assertSame(route('offers.feature', [$offer, 'pinterest']), OfferFeatures::urlForGeneration($generation));
    }
}
