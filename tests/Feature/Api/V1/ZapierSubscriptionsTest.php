<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API roadmap item #9 — the small backend surface the Zapier platform
 * app's REST Hook triggers use to subscribe/unsubscribe (see
 * ZapierSubscriptionsController and integrations/zapier/).
 */
class ZapierSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function authHeaders(string $plainText): array
    {
        return ['Authorization' => "Bearer {$plainText}"];
    }

    public function test_subscribing_creates_a_webhook_endpoint_for_the_requested_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Zapier')['plainText'];

        $response = $this->postJson('/api/v1/zapier/subscriptions', [
            'target_url' => 'https://hooks.zapier.com/hooks/standard/123/abc',
            'event' => 'crm_contact.created',
        ], $this->authHeaders($token));

        $response->assertStatus(201)->assertJsonStructure(['id']);
        $this->assertDatabaseHas('webhook_endpoints', [
            'user_id' => $user->id,
            'url' => 'https://hooks.zapier.com/hooks/standard/123/abc',
        ]);
        $endpoint = WebhookEndpoint::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['crm_contact.created'], $endpoint->events);
    }

    public function test_unsubscribing_removes_the_webhook_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Zapier')['plainText'];
        $endpoint = WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://hooks.zapier.com/x', 'events' => ['crm_contact.created'], 'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true]);

        $this->deleteJson("/api/v1/zapier/subscriptions/{$endpoint->id}", [], $this->authHeaders($token))
            ->assertOk();

        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
    }

    public function test_a_user_cannot_unsubscribe_someone_elses_endpoint(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        $token = ApiToken::generate($intruder, 'Zapier')['plainText'];
        $endpoint = WebhookEndpoint::create(['user_id' => $owner->id, 'url' => 'https://hooks.zapier.com/x', 'events' => ['crm_contact.created'], 'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true]);

        $this->deleteJson("/api/v1/zapier/subscriptions/{$endpoint->id}", [], $this->authHeaders($token))
            ->assertStatus(404);
        $this->assertDatabaseHas('webhook_endpoints', ['id' => $endpoint->id]);
    }

    public function test_a_team_seat_cannot_create_a_zapier_subscription(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');
        $token = ApiToken::generate($seat, 'Zapier')['plainText'];

        $this->postJson('/api/v1/zapier/subscriptions', [
            'target_url' => 'https://hooks.zapier.com/x', 'event' => 'crm_contact.created',
        ], $this->authHeaders($token))->assertStatus(403);
    }

    public function test_subscribing_to_an_unknown_event_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Zapier')['plainText'];

        $this->postJson('/api/v1/zapier/subscriptions', [
            'target_url' => 'https://hooks.zapier.com/x', 'event' => 'not_a_real_event',
        ], $this->authHeaders($token))->assertStatus(422);
    }
}
