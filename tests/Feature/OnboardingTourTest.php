<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The skippable, resumable welcome tour: it starts by itself until the user
 * finishes or skips it once, and "Take the tour" restarts it.
 */
class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tour_starts_by_itself_for_a_new_customer_on_get_started(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('window.AffilStackTour', false)
            ->assertSee('autoStart: true', false)
            ->assertSee('Take the tour');
    }

    public function test_the_tour_does_not_auto_start_on_other_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('offers.index'))
            ->assertOk()
            ->assertSee('autoStart: false', false);
    }

    public function test_completing_the_tour_stops_it_starting_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('onboarding.complete'))->assertOk()->assertJson(['status' => 'completed']);

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertFalse($user->fresh()->shouldSeeOnboardingTour());
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertSee('autoStart: false', false);
    }

    public function test_dismissing_the_tour_is_remembered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('onboarding.dismiss'))->assertOk()->assertJson(['status' => 'dismissed']);

        $this->assertNotNull($user->fresh()->onboarding_dismissed_at);
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertSee('autoStart: false', false);
    }

    public function test_take_the_tour_clears_the_flags_and_restarts_it_on_get_started(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['onboarding_completed_at' => now(), 'onboarding_dismissed_at' => now()])->save();

        $this->actingAs($user)->get(route('onboarding.restart'))->assertRedirect(route('dashboard', ['tour' => 1]));

        $this->assertTrue($user->fresh()->shouldSeeOnboardingTour());
        $this->actingAs($user->fresh())->get(route('dashboard', ['tour' => 1]))->assertSee('autoStart: true', false);
    }

    public function test_the_tour_requires_a_signed_in_user(): void
    {
        $this->postJson(route('onboarding.complete'))->assertUnauthorized();
    }

    public function test_a_team_seat_gets_the_tour_on_its_product_page_and_can_finish_it(): void
    {
        $owner = User::factory()->create();
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'Acme', 'product_url' => 'https://a.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('offers.show', $offer))
            ->assertOk()
            ->assertSee('autoStart: true', false)
            ->assertSee('isSeat: true', false);

        $this->actingAs($seat)->postJson(route('onboarding.complete'))->assertOk();
        $this->assertNotNull($seat->fresh()->onboarding_completed_at);
    }

    public function test_affiliate_only_accounts_never_get_the_customer_tour(): void
    {
        $user = User::factory()->create(['is_affiliate_only' => true]);

        $this->actingAs($user)->get(route('partner.dashboard'))
            ->assertOk()
            ->assertDontSee('window.AffilStackTour', false);
    }
}
