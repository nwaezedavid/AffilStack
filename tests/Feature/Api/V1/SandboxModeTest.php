<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * API roadmap item #5 — sandbox tokens (see ApiToken::is_sandbox,
 * OffersController, CrmContactsController, MeterApiUsage). Sandbox and
 * live data are mutually invisible and sandbox calls never spend real
 * money/credits, mirroring Stripe's test-mode/live-mode split.
 */
class SandboxModeTest extends TestCase
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

    public function test_a_sandbox_token_creates_an_offer_synchronously_with_canned_data_and_spends_nothing(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 0, 'api_wallet_balance_cents' => 0]);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Sandbox', isSandbox: true)['plainText'];

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'Test Product', 'product_url' => 'https://example.com', 'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($token));

        $response->assertStatus(201)->assertJson(['status' => 'ready', 'is_sandbox' => true]);
        $this->assertNotNull($response->json('ideal_customer_summary'));
        $this->assertSame(0, $user->fresh()->credits_balance);
        $this->assertSame(0, $user->fresh()->api_wallet_balance_cents);
        Queue::assertNothingPushed();
    }

    public function test_a_sandbox_token_never_sees_live_offers_and_vice_versa(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $liveOffer = Offer::create(['user_id' => $user->id, 'is_sandbox' => false, 'product_name' => 'Live', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $sandboxOffer = Offer::create(['user_id' => $user->id, 'is_sandbox' => true, 'product_name' => 'Sandbox', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $liveToken = ApiToken::generate($user, 'Live')['plainText'];
        $sandboxToken = ApiToken::generate($user, 'Sandbox', isSandbox: true)['plainText'];

        $liveIds = collect($this->getJson('/api/v1/offers', $this->authHeaders($liveToken))->json('data'))->pluck('id');
        $sandboxIds = collect($this->getJson('/api/v1/offers', $this->authHeaders($sandboxToken))->json('data'))->pluck('id');

        $this->assertTrue($liveIds->contains($liveOffer->id));
        $this->assertFalse($liveIds->contains($sandboxOffer->id));
        $this->assertTrue($sandboxIds->contains($sandboxOffer->id));
        $this->assertFalse($sandboxIds->contains($liveOffer->id));
    }

    public function test_a_live_token_gets_404_for_a_sandbox_offer_id(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $sandboxOffer = Offer::create(['user_id' => $user->id, 'is_sandbox' => true, 'product_name' => 'Sandbox', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $liveToken = ApiToken::generate($user, 'Live')['plainText'];

        $this->getJson("/api/v1/offers/{$sandboxOffer->id}", $this->authHeaders($liveToken))->assertStatus(404);
    }

    public function test_a_sandbox_token_creates_a_flagged_crm_contact_and_bypasses_the_plan_contact_limit(): void
    {
        $plan = Plan::factory()->create(['contact_limit' => 1]);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        CrmContact::create(['user_id' => $user->id, 'name' => 'Existing', 'source' => 'manual', 'status' => 'new']);
        $sandboxToken = ApiToken::generate($user, 'Sandbox', isSandbox: true)['plainText'];

        // Already at the plan's real limit of 1 — a live call would 402
        // here (see ApiV1EndpointsTest), but sandbox data never counts
        // against it.
        $response = $this->postJson('/api/v1/crm-contacts', ['name' => 'Sandbox Lead'], $this->authHeaders($sandboxToken));

        $response->assertStatus(201)->assertJson(['is_sandbox' => true]);
    }

    public function test_a_sandbox_contact_does_not_appear_in_a_live_tokens_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $sandboxToken = ApiToken::generate($user, 'Sandbox', isSandbox: true)['plainText'];
        $liveToken = ApiToken::generate($user, 'Live')['plainText'];

        $this->postJson('/api/v1/crm-contacts', ['name' => 'Sandbox Lead'], $this->authHeaders($sandboxToken))->assertStatus(201);

        $liveList = $this->getJson('/api/v1/crm-contacts', $this->authHeaders($liveToken));

        $this->assertCount(0, $liveList->json('data'));
    }
}
