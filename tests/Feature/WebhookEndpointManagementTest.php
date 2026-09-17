<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit gap #7 — the dashboard side of outbound webhooks (the "API Access"
 * page's push counterpart to its existing pull-only tokens). Owner-only,
 * unlike API tokens: two of the four subscribable events (referrals,
 * earnings) aren't something a team seat has its own view of at all.
 */
class WebhookEndpointManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_an_owner_can_create_a_webhook_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('api-access.webhooks.store'), [
                'url' => 'https://example.com/hooks/affilstack',
                'events' => ['generation.completed', 'earning.recorded'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $endpoint = WebhookEndpoint::where('user_id', $user->id)->sole();
        $this->assertSame('https://example.com/hooks/affilstack', $endpoint->url);
        $this->assertSame(['generation.completed', 'earning.recorded'], $endpoint->events);
        $this->assertTrue($endpoint->is_active);
        $this->assertStringStartsWith('whsec_', $endpoint->secret);
    }

    public function test_creating_a_webhook_endpoint_requires_a_valid_url(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('api-access.webhooks.store'), [
                'url' => 'not-a-url',
                'events' => ['generation.completed'],
            ])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_creating_a_webhook_endpoint_rejects_an_unknown_event_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('api-access.webhooks.store'), [
                'url' => 'https://example.com/hooks',
                'events' => ['not_a_real_event'],
            ])
            ->assertSessionHasErrors('events.0');

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_creating_a_webhook_endpoint_requires_at_least_one_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->post(route('api-access.webhooks.store'), [
                'url' => 'https://example.com/hooks',
                'events' => [],
            ])
            ->assertSessionHasErrors('events');
    }

    public function test_an_owner_can_toggle_and_delete_their_own_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $endpoint = WebhookEndpoint::create([
            'user_id' => $user->id, 'url' => 'https://example.com/hooks',
            'secret' => WebhookEndpoint::generateSecret(), 'events' => ['generation.completed'], 'is_active' => true,
        ]);

        $this->actingAs($user)->patch(route('api-access.webhooks.toggle', $endpoint))->assertRedirect();
        $this->assertFalse($endpoint->fresh()->is_active);

        $this->actingAs($user)->delete(route('api-access.webhooks.destroy', $endpoint))->assertRedirect();
        $this->assertModelMissing($endpoint);
    }

    public function test_a_user_cannot_toggle_or_delete_another_users_endpoint(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        $endpoint = WebhookEndpoint::create([
            'user_id' => $owner->id, 'url' => 'https://example.com/hooks',
            'secret' => WebhookEndpoint::generateSecret(), 'events' => ['generation.completed'], 'is_active' => true,
        ]);

        $this->actingAs($intruder)->patch(route('api-access.webhooks.toggle', $endpoint))->assertForbidden();
        $this->actingAs($intruder)->delete(route('api-access.webhooks.destroy', $endpoint))->assertForbidden();

        $this->assertTrue($endpoint->fresh()->is_active);
    }

    public function test_deleting_an_endpoint_cascades_its_delivery_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $endpoint = WebhookEndpoint::create([
            'user_id' => $user->id, 'url' => 'https://example.com/hooks',
            'secret' => WebhookEndpoint::generateSecret(), 'events' => ['generation.completed'], 'is_active' => true,
        ]);
        $delivery = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'generation.completed', 'payload' => [], 'status' => 'delivered']);

        $this->actingAs($user)->delete(route('api-access.webhooks.destroy', $endpoint));

        $this->assertModelMissing($delivery);
    }

    public function test_a_team_seat_cannot_manage_webhook_endpoints(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)
            ->post(route('api-access.webhooks.store'), ['url' => 'https://example.com/hooks', 'events' => ['generation.completed']])
            ->assertForbidden();

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_the_api_access_page_hides_the_webhooks_section_from_a_team_seat(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)
            ->get(route('api-access.index'))
            ->assertOk()
            ->assertDontSee('Outbound webhooks');
    }

    public function test_the_api_access_page_shows_the_webhooks_section_to_the_owner(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('Outbound webhooks');
    }
}
