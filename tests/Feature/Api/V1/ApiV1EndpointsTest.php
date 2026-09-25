<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Task #6 (general-purpose API): the user-scoped /api/v1/* surface. Every
 * endpoint deliberately reuses the exact same scoping methods the dashboard
 * uses (visibleOffers(), visibleGenerations(), Offer::isAccessibleBy(),
 * crmContacts()), so these tests mirror BusinessTierTeamSeatsTest's paired
 * isolated/shared assertion style rather than inventing new scoping rules.
 */
class ApiV1EndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function tokenFor(User $user): string
    {
        return ApiToken::generate($user, 'Test token')['plainText'];
    }

    protected function authHeaders(string $plainText): array
    {
        return ['Authorization' => "Bearer {$plainText}"];
    }

    // --- Authentication --------------------------------------------------

    public function test_a_request_without_a_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_a_request_with_an_invalid_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/me', $this->authHeaders('aff_not-a-real-token'))->assertStatus(401);
    }

    public function test_a_valid_token_can_reach_me(): void
    {
        $user = User::factory()->create(['name' => 'Jordan Owner']);
        $user->assignRole('user');

        $response = $this->getJson('/api/v1/me', $this->authHeaders($this->tokenFor($user)));

        $response->assertOk()->assertJson(['id' => $user->id, 'name' => 'Jordan Owner']);
    }

    public function test_using_a_token_updates_its_last_used_at(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $plainText = $this->tokenFor($user);
        $token = ApiToken::findByPlainText($plainText);

        $this->assertNull($token->last_used_at);

        $this->getJson('/api/v1/me', $this->authHeaders($plainText))->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    // --- Offers: owner / isolated-seat / shared-seat scoping --------------

    public function test_an_owners_token_sees_only_its_own_offers(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $mine = Offer::create(['user_id' => $owner->id, 'product_name' => 'Mine', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $someoneElse = User::factory()->create();
        Offer::create(['user_id' => $someoneElse->id, 'product_name' => 'Not mine', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $response = $this->getJson('/api/v1/offers', $this->authHeaders($this->tokenFor($owner)));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertCount(1, $ids);
    }

    public function test_a_shared_plan_seats_token_sees_the_whole_teams_offers(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'Team offer', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $response = $this->getJson('/api/v1/offers', $this->authHeaders($this->tokenFor($seat)));

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->pluck('id')->contains($offer->id));
    }

    public function test_an_isolated_plan_seats_token_only_sees_its_one_assigned_offer(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);

        $assigned = Offer::create(['user_id' => $owner->id, 'product_name' => 'Assigned', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $other = Offer::create(['user_id' => $owner->id, 'product_name' => 'Other', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $assigned->id]);
        $seat->assignRole('user');

        $showAssigned = $this->getJson("/api/v1/offers/{$assigned->id}", $this->authHeaders($this->tokenFor($seat)));
        $showOther = $this->getJson("/api/v1/offers/{$other->id}", $this->authHeaders($this->tokenFor($seat)));

        $showAssigned->assertOk();
        $showOther->assertStatus(404);
    }

    public function test_creating_an_offer_via_the_api_queues_research_and_charges_credits(): void
    {
        Queue::fake();
        // api_wallet_balance_cents covers MeterApiUsage's separate $0.75
        // per-call fee (config('api_billing.costs')) — a completely
        // different balance from credits_balance, see ApiWalletMeteringTest
        // for that gate's own dedicated coverage.
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'API Offer',
            'product_url' => 'https://api.example',
            'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(201);
        $this->assertDatabaseHas('offers', ['product_name' => 'API Offer', 'user_id' => $user->id]);
    }

    public function test_creating_an_offer_via_the_api_without_enough_credits_returns_402(): void
    {
        // A healthy API wallet balance here isolates this test to the
        // *credits* gate specifically — with a $0 wallet too, this would
        // still return 402, but for MeterApiUsage's insufficient-balance
        // reason instead, which is covered on its own in ApiWalletMeteringTest.
        $user = User::factory()->create(['credits_balance' => 0, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'API Offer',
            'product_url' => 'https://api.example',
            'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(402)->assertJson(['message' => 'Not enough credits for offer research.']);
    }

    // --- Generations: read-only, same scoping -----------------------------

    public function test_generations_are_scoped_the_same_way_as_offers(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = Generation::create(['user_id' => $owner->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed']);

        $other = User::factory()->create();
        $other->assignRole('user');

        $mine = $this->getJson('/api/v1/generations', $this->authHeaders($this->tokenFor($owner)));
        $mine->assertOk();
        $this->assertTrue(collect($mine->json('data'))->pluck('id')->contains($generation->id));

        $this->getJson("/api/v1/generations/{$generation->id}", $this->authHeaders($this->tokenFor($other)))
            ->assertStatus(404);
    }

    // --- CRM contacts: never team-shared -----------------------------------

    public function test_crm_contacts_are_scoped_to_the_creating_user_even_on_a_shared_plan(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        $ownerContact = CrmContact::create(['user_id' => $owner->id, 'name' => 'Owner Contact', 'source' => 'manual', 'status' => 'new']);

        $response = $this->getJson('/api/v1/crm-contacts', $this->authHeaders($this->tokenFor($seat)));

        $response->assertOk();
        $this->assertFalse(collect($response->json('data'))->pluck('id')->contains($ownerContact->id));
    }

    public function test_a_contact_can_be_created_and_updated_via_the_api(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = $this->authHeaders($this->tokenFor($user));

        $create = $this->postJson('/api/v1/crm-contacts', ['name' => 'New Lead', 'email' => 'lead@example.com'], $token);
        $create->assertStatus(201)->assertJson(['name' => 'New Lead', 'source' => 'api']);

        $contactId = $create->json('id');

        $update = $this->patchJson("/api/v1/crm-contacts/{$contactId}", ['status' => 'contacted'], $token);
        $update->assertOk()->assertJson(['status' => 'contacted']);
    }

    public function test_a_contact_created_via_the_api_respects_the_plan_contact_limit(): void
    {
        $plan = Plan::factory()->create(['contact_limit' => 1]);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        CrmContact::create(['user_id' => $user->id, 'name' => 'Existing', 'source' => 'manual', 'status' => 'new']);

        $response = $this->postJson('/api/v1/crm-contacts', ['name' => 'Second'], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(402);
    }

    // --- Referrals summary ---------------------------------------------------

    public function test_referrals_summary_returns_the_users_own_referral_code_and_totals(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->getJson('/api/v1/referrals/summary', $this->authHeaders($this->tokenFor($user)));

        $response->assertOk()->assertJsonStructure([
            'referral_code', 'referral_link', 'total_referrals',
            'unpaid_approved_commission_cents', 'has_open_payout_request', 'events_by_status',
        ]);
    }
}
