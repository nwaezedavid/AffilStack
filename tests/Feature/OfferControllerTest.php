<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Item 3 (simplified product-to-leads flow): the optional affiliate_link
 * input on offer creation, and the "Ready to start?" call-to-action + per-
 * channel recommendation badges added to offers/show.blade.php so a
 * finished research result leads straight into the next concrete action
 * instead of just displaying data.
 */
class OfferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_an_offer_with_an_optional_affiliate_link(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $response = $this->actingAs($user)->post(route('offers.store'), [
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'affiliate_link' => 'https://shareasale.com/r.cfm?u=me',
        ]);

        $offer = Offer::where('user_id', $user->id)->first();
        $response->assertRedirect(route('offers.show', $offer));
        $this->assertSame('https://shareasale.com/r.cfm?u=me', $offer->affiliate_link);
    }

    public function test_store_works_without_an_affiliate_link(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $response = $this->actingAs($user)->post(route('offers.store'), [
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull(Offer::where('user_id', $user->id)->first()->affiliate_link);
    }

    public function test_store_rejects_an_invalid_affiliate_link(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $response = $this->actingAs($user)->post(route('offers.store'), [
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'affiliate_link' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('affiliate_link');
        $this->assertDatabaseMissing('offers', ['user_id' => $user->id]);
    }

    protected function readyOffer(User $user, array $overrides = []): Offer
    {
        return Offer::create(array_merge([
            'user_id' => $user->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
            'ideal_customer_summary' => 'Busy parents.',
            'recommended_angle' => 'Save time.',
        ], $overrides));
    }

    public function test_show_offers_a_google_maps_cta_prefilled_with_the_ais_suggestion(): void
    {
        $user = User::factory()->create();
        $offer = $this->readyOffer($user, [
            'recommended_channel' => 'google_maps',
            'suggested_maps_niche' => 'dentists',
            'suggested_maps_location' => 'Austin, TX',
        ]);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertOk();
        $response->assertSee('Find local leads on Google Maps', false);
        // Blade's {{ }} runs the URL through e() same as any other output,
        // turning "&" into "&amp;" in the rendered href — match that shape
        // rather than the raw route() string.
        $response->assertSee(e(route('leads.index', ['niche' => 'dentists', 'location' => 'Austin, TX'])), false);
    }

    public function test_show_offers_a_jump_link_cta_and_badge_for_a_non_maps_recommended_channel(): void
    {
        $user = User::factory()->create();
        $offer = $this->readyOffer($user, ['recommended_channel' => 'linkedin']);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertOk();
        $response->assertSee('#linkedin-card', false);
        $response->assertSee('Jump to LinkedIn', false);
        $response->assertSee('⭐ Recommended', false);
    }

    public function test_show_marks_the_secondary_channel_as_a_good_alternative(): void
    {
        $user = User::factory()->create();
        $offer = $this->readyOffer($user, [
            'recommended_channel' => 'linkedin',
            'research_data' => ['secondary_channel' => 'pinterest'],
        ]);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertOk();
        $response->assertSee('Good alternative', false);
    }

    public function test_show_renders_the_affiliate_link_when_present(): void
    {
        $user = User::factory()->create();
        $offer = $this->readyOffer($user, ['affiliate_link' => 'https://shareasale.com/r.cfm?u=me']);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertOk();
        $response->assertSee('https://shareasale.com/r.cfm?u=me', false);
    }
}
