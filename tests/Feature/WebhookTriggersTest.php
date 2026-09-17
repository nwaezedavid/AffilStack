<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\Earning;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit gap #7: the general-purpose API was pull-only. Covers all four
 * subscribable events (the broadest scope was chosen over a narrower
 * default) firing from their real trigger points, plus the delivery job's
 * signing and retry behavior.
 */
class WebhookTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function endpointFor(User $user, array $events, bool $active = true): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'user_id' => $user->id,
            'url' => 'https://example.com/hooks/affilstack',
            'secret' => WebhookEndpoint::generateSecret(),
            'events' => $events,
            'is_active' => $active,
        ]);
    }

    public function test_a_generation_transitioning_to_completed_fires_the_webhook(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $endpoint = $this->endpointFor($user, ['generation.completed']);
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Widget', 'product_url' => 'https://example.com/w', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'pending']);

        $generation->update(['status' => 'completed']);

        $this->assertSame(1, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->where('event', 'generation.completed')->count());
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hooks/affilstack'
            && $request['data']['generation_id'] === $generation->id);
    }

    public function test_a_generation_created_already_completed_also_fires_the_webhook(): void
    {
        // OfferResearchService creates its Generation row already completed
        // in one step, rather than updating a pending row — see
        // Generation::booted().
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['generation.completed']);
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Widget', 'product_url' => 'https://example.com/w', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'research', 'status' => 'completed']);

        $this->assertSame(1, WebhookDelivery::where('event', 'generation.completed')->count());
    }

    public function test_a_new_crm_contact_fires_the_webhook(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['crm_contact.created']);

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $this->assertSame(1, WebhookDelivery::where('event', 'crm_contact.created')->count());
    }

    public function test_a_referral_converting_fires_the_webhook(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $referrer = User::factory()->create();
        $this->endpointFor($referrer, ['referral.converted']);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id, 'status' => 'signed_up']);

        $referral->update(['status' => 'converted', 'converted_at' => now()]);

        $this->assertSame(1, WebhookDelivery::where('event', 'referral.converted')->count());
    }

    public function test_a_referral_events_status_change_alone_does_not_refire_the_webhook(): void
    {
        // Guard against wasChanged('status') false positives — updating an
        // already-converted referral for an unrelated reason must not
        // re-fire the webhook.
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $referrer = User::factory()->create();
        $this->endpointFor($referrer, ['referral.converted']);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id, 'status' => 'converted', 'converted_at' => now()]);

        $referral->touch();

        $this->assertSame(0, WebhookDelivery::where('event', 'referral.converted')->count());
    }

    public function test_a_new_earning_fires_the_webhook(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['earning.recorded']);

        Earning::create(['user_id' => $user->id, 'network' => 'ShareASale', 'source' => 'manual', 'amount_cents' => 1000, 'currency' => 'USD', 'status' => 'pending', 'converted_at' => now()]);

        $this->assertSame(1, WebhookDelivery::where('event', 'earning.recorded')->count());
    }

    public function test_an_endpoint_not_subscribed_to_the_event_receives_nothing(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['earning.recorded']); // not subscribed to CRM events

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $this->assertSame(0, WebhookDelivery::count());
        Http::assertNothingSent();
    }

    public function test_a_disabled_endpoint_receives_nothing(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['crm_contact.created'], active: false);

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $this->assertSame(0, WebhookDelivery::count());
        Http::assertNothingSent();
    }

    public function test_another_users_endpoint_never_receives_this_users_events(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->endpointFor($otherUser, ['crm_contact.created']);

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_a_successful_delivery_is_correctly_signed_and_marked_delivered(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $user = User::factory()->create();
        $endpoint = $this->endpointFor($user, ['crm_contact.created']);

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $delivery = WebhookDelivery::sole();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);

        $endpoint->refresh();
        $this->assertNotNull($endpoint->last_triggered_at);

        Http::assertSent(function ($request) use ($endpoint) {
            $expected = hash_hmac('sha256', $request->body(), $endpoint->secret);

            return $request->hasHeader('X-AffilStack-Signature', $expected)
                && $request->hasHeader('X-AffilStack-Event', 'crm_contact.created');
        });
    }

    public function test_a_failed_delivery_retries_and_eventually_gives_up(): void
    {
        config(['webhooks.max_attempts' => 2, 'webhooks.retry_backoff_minutes' => [1]]);
        Http::fake(['example.com/*' => Http::response('server error', 500)]);

        $user = User::factory()->create();
        $this->endpointFor($user, ['crm_contact.created']);

        CrmContact::create(['user_id' => $user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        // Two attempts total: the initial send plus one retry, both
        // executed inline since tests run the "sync" queue driver — a
        // real queue worker would honor the delay between them instead.
        $delivery = WebhookDelivery::sole();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(500, $delivery->response_status);

        Http::assertSentCount(2);
    }

    public function test_a_referral_events_referrer_still_sees_a_webhook_even_without_the_events_relation_loaded(): void
    {
        // Sanity check that ReferralEvent (a sibling model, not itself a
        // webhook trigger) doesn't accidentally interfere with Referral's
        // hook when both are touched in the same request.
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $referrer = User::factory()->create();
        $this->endpointFor($referrer, ['referral.converted']);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id, 'status' => 'signed_up']);
        ReferralEvent::factory()->create(['referral_id' => $referral->id]);

        $referral->update(['status' => 'converted', 'converted_at' => now()]);

        $this->assertSame(1, WebhookDelivery::where('event', 'referral.converted')->count());
    }
}
