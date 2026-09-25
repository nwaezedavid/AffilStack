<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookDelivery;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * API roadmap item #10 — replaying a webhook delivery (see
 * ApiAccessController::replayWebhookDelivery()).
 */
class WebhookDeliveryReplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_replaying_a_delivery_creates_a_new_pending_delivery_and_dispatches_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->assignRole('user');
        $endpoint = WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://example.com/hook', 'events' => ['crm_contact.created'], 'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true]);
        $delivery = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'crm_contact.created', 'payload' => ['name' => 'Lead'], 'status' => 'failed', 'attempts' => 5]);

        $response = $this->actingAs($user)->post(route('api-access.webhooks.deliveries.replay', $delivery));

        $response->assertRedirect();
        $this->assertSame(2, WebhookDelivery::count());
        $replay = WebhookDelivery::where('id', '!=', $delivery->id)->firstOrFail();
        $this->assertSame('pending', $replay->status);
        $this->assertSame(['name' => 'Lead'], $replay->payload);
        Queue::assertPushed(SendWebhookDelivery::class, fn ($job) => $job->delivery->is($replay));
    }

    public function test_a_user_cannot_replay_someone_elses_delivery(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        $endpoint = WebhookEndpoint::create(['user_id' => $owner->id, 'url' => 'https://example.com/hook', 'events' => ['crm_contact.created'], 'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true]);
        $delivery = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'crm_contact.created', 'payload' => [], 'status' => 'failed']);

        $this->actingAs($intruder)->post(route('api-access.webhooks.deliveries.replay', $delivery))
            ->assertStatus(403);

        $this->assertSame(1, WebhookDelivery::count());
    }
}
