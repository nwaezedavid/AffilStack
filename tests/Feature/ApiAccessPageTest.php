<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6: the dashboard side of the general-purpose API. Deliberately open
 * to team seats — unlike the browser extension's routes — since a seat's
 * own token naturally only ever sees what that seat can already see.
 */
class ApiAccessPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_user_can_generate_list_and_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('api-access.index'))->assertOk();

        $this->actingAs($user)
            ->post(route('api-access.tokens.store'), ['name' => 'Zapier'])
            ->assertRedirect();

        $token = ApiToken::where('user_id', $user->id)->where('name', 'Zapier')->firstOrFail();
        $this->assertSame('user', $token->type);

        $this->actingAs($user)
            ->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('Zapier');

        $this->actingAs($user)
            ->delete(route('api-access.tokens.destroy', $token))
            ->assertRedirect();

        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
    }

    public function test_a_user_cannot_revoke_someone_elses_token(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $token = ApiToken::generate($owner, 'Owner token')['token'];

        $this->actingAs($intruder)
            ->delete(route('api-access.tokens.destroy', $token))
            ->assertForbidden();

        $this->assertDatabaseHas('api_tokens', ['id' => $token->id]);
    }

    public function test_the_page_only_lists_user_type_tokens_not_admin_ones(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        ApiToken::generate($admin, 'Admin token', 'admin');
        ApiToken::generate($admin, 'Personal token', 'user');

        $this->actingAs($admin)
            ->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('Personal token')
            ->assertDontSee('Admin token');
    }

    public function test_an_isolated_plan_seat_can_reach_the_api_access_page(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('api-access.index'))->assertOk();

        $this->actingAs($seat)
            ->post(route('api-access.tokens.store'), ['name' => 'My integration'])
            ->assertRedirect();

        $this->assertDatabaseHas('api_tokens', ['user_id' => $seat->id, 'name' => 'My integration']);
    }

    public function test_a_shared_plan_seat_can_reach_the_api_access_page(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('api-access.index'))->assertOk();
    }

    // --- API wallet section (see ApiWalletManagerTest/ApiWalletControllerTest for the feature's own logic) ---

    public function test_the_page_shows_the_owners_api_wallet_balance(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 1234]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('api-access.index'))->assertOk()->assertSee('$12.34');
    }

    public function test_a_seat_does_not_see_the_owners_wallet_section(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create(['api_wallet_balance_cents' => 1234]);
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        // "API wallet" itself still appears in the endpoint pricing note
        // (context-adjusted for a seat — see the view), so assert against
        // the wallet section's own controls instead, which stay owner-only.
        $this->actingAs($seat)->get(route('api-access.index'))->assertOk()->assertDontSee('Auto-recharge');
    }

    public function test_the_page_shows_a_low_balance_warning_at_or_below_the_threshold(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 500]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('api-access.index'))->assertOk()->assertSee('Running low');
    }
}
