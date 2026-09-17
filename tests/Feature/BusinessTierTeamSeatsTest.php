<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Calendar\ContentCalendarService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The Business/Team tier design: a "shared" plan (Plan::seat_mode) gives
 * every seat access to the whole account's offers rather than the
 * original agency model's one-offer-per-seat isolation, with a per-seat
 * seat_role ('member' draft-only, 'manager' can also publish) instead of
 * the old flat "seats can never publish" rule. Every isolated-plan
 * assertion here is a regression check — that behavior must stay exactly
 * as item 10 (agency/team seats) originally shipped it.
 */
class BusinessTierTeamSeatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function ownerOnPlan(Plan $plan): User
    {
        $owner = User::factory()->create(['credits_balance' => 1000]);
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);

        return $owner->fresh();
    }

    protected function seat(User $owner, array $attributes = []): User
    {
        $seat = User::factory()->create(array_merge([
            'agency_owner_id' => $owner->id,
        ], $attributes));
        $seat->assignRole('user');

        return $seat->fresh();
    }

    // --- Offer::isAccessibleBy() -------------------------------------------------

    public function test_a_shared_plan_seat_can_access_any_of_the_owners_offers(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $seat = $this->seat($owner, ['seat_role' => 'member']);

        $offerOne = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $offerTwo = Offer::create(['user_id' => $owner->id, 'product_name' => 'B', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $this->assertTrue($offerOne->isAccessibleBy($seat));
        $this->assertTrue($offerTwo->isAccessibleBy($seat));
    }

    public function test_an_isolated_plan_seat_can_only_access_its_one_assigned_offer(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = $this->ownerOnPlan($plan);

        $assigned = Offer::create(['user_id' => $owner->id, 'product_name' => 'Assigned', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $other = Offer::create(['user_id' => $owner->id, 'product_name' => 'Other', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $seat = $this->seat($owner, ['seat_offer_id' => $assigned->id]);

        $this->assertTrue($assigned->isAccessibleBy($seat));
        $this->assertFalse($other->isAccessibleBy($seat));
    }

    // --- Dashboard redirect --------------------------------------------------

    public function test_a_shared_plan_seat_lands_on_the_offers_list_not_a_single_offer(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $seat = $this->seat($owner, ['seat_role' => 'member']);

        $this->actingAs($seat)->get(route('dashboard'))->assertRedirect(route('offers.index'));
    }

    public function test_an_isolated_plan_seat_still_lands_on_its_one_offer(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = $this->seat($owner, ['seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('dashboard'))->assertRedirect(route('offers.show', $offer));
    }

    // --- Offers list / creation route gating ---------------------------------

    public function test_a_shared_plan_seat_can_view_and_create_offers(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $seat = $this->seat($owner, ['seat_role' => 'member']);
        Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $this->actingAs($seat)->get(route('offers.index'))->assertOk()->assertSee('A');
    }

    public function test_an_isolated_plan_seat_is_forbidden_from_the_offers_list_and_creation(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = $this->seat($owner, ['seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('offers.index'))->assertForbidden();
        $this->actingAs($seat)->get(route('offers.create'))->assertForbidden();
    }

    public function test_an_offer_created_by_a_shared_plan_seat_is_owned_by_the_account_owner(): void
    {
        Queue::fake();
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $seat = $this->seat($owner, ['seat_role' => 'member', 'credits_balance' => 0]);

        $this->actingAs($seat)->post(route('offers.store'), [
            'product_name' => 'Team Offer',
            'product_url' => 'https://team.example',
            'affiliate_network' => 'ShareASale',
        ])->assertRedirect();

        $offer = Offer::where('product_name', 'Team Offer')->firstOrFail();
        $this->assertSame($owner->id, $offer->user_id);
    }

    // --- Publish / mark-started gating ---------------------------------------

    public function test_a_manager_seat_can_mark_content_published_but_a_member_cannot(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = Generation::create(['user_id' => $owner->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed']);

        $member = $this->seat($owner, ['seat_role' => 'member']);
        $manager = $this->seat($owner, ['seat_role' => 'manager']);

        $this->actingAs($member)
            ->patch(route('calendar.update', $generation), ['calendar_status' => 'published'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('calendar.update', $generation), ['calendar_status' => 'published'])
            ->assertRedirect();

        $this->assertSame('published', $generation->fresh()->calendar_status);
    }

    public function test_a_manager_seat_can_mark_a_dm_sequence_started_but_a_member_cannot(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = Generation::create(['user_id' => $owner->id, 'offer_id' => $offer->id, 'module' => 'linkedin_dm_sequence', 'status' => 'completed']);

        $member = $this->seat($owner, ['seat_role' => 'member']);
        $manager = $this->seat($owner, ['seat_role' => 'manager']);

        $this->actingAs($member)
            ->post(route('generations.nurture-started', $generation))
            ->assertForbidden();

        $this->assertNull($generation->fresh()->published_at);

        $this->actingAs($manager)
            ->post(route('generations.nurture-started', $generation))
            ->assertRedirect();

        $this->assertNotNull($generation->fresh()->published_at);
    }

    public function test_an_isolated_plan_seat_still_cannot_publish_regardless_of_seat_role(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = Generation::create(['user_id' => $owner->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed']);
        // seat_role is meaningless on an isolated plan — set to 'manager' to prove it's ignored.
        $seat = $this->seat($owner, ['seat_offer_id' => $offer->id, 'seat_role' => 'manager']);

        $this->actingAs($seat)
            ->patch(route('calendar.update', $generation), ['calendar_status' => 'published'])
            ->assertForbidden();
    }

    // --- visibleGenerations() / the shared calendar ---------------------------

    public function test_the_calendar_shows_every_teammates_generations_on_a_shared_plan(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);
        $manager = $this->seat($owner, ['seat_role' => 'manager']);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        Generation::create(['user_id' => $owner->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed', 'output_meta' => ['title' => 'Owner post']]);

        $this->actingAs($manager)->get(route('calendar.index'))->assertOk();

        $this->assertCount(1, app(ContentCalendarService::class)->entriesFor($manager));
    }

    // --- TeamController::store() ----------------------------------------------

    public function test_creating_a_seat_on_a_shared_plan_requires_a_role_and_sets_no_offer(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->ownerOnPlan($plan);

        $this->actingAs($owner)->post(route('team.store'), [
            'name' => 'Jordan',
            'email' => 'jordan@example.com',
            'seat_role' => 'manager',
        ])->assertRedirect();

        $created = User::where('email', 'jordan@example.com')->firstOrFail();
        $this->assertSame($owner->id, $created->agency_owner_id);
        $this->assertSame('manager', $created->seat_role);
        $this->assertNull($created->seat_offer_id);
    }

    public function test_creating_a_seat_on_an_isolated_plan_still_requires_an_offer(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = $this->ownerOnPlan($plan);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $this->actingAs($owner)->post(route('team.store'), [
            'name' => 'Jordan',
            'email' => 'jordan@example.com',
            'offer_id' => $offer->id,
        ])->assertRedirect();

        $created = User::where('email', 'jordan@example.com')->firstOrFail();
        $this->assertSame($offer->id, $created->seat_offer_id);
        $this->assertNull($created->seat_role);
    }
}
