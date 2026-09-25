<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Item 3 (simplified product-to-leads flow): LeadFinderController::index()
 * now reads niche/location off the query string — the hand-off from the
 * Offer Research "Ready to start?" CTA (see offers/show.blade.php) — and
 * runs the Google Maps search immediately instead of making the user press
 * "Search" again on an already-filled-in form. A search typed by hand still
 * goes through search() (POST), which is unaffected by this change.
 */
class LeadFinderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function planWithGoogleMaps(): Plan
    {
        return Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['google_maps'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function planWithoutGoogleMaps(): Plan
    {
        return Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Test plan',
            'price_monthly_cents' => 2700, 'price_yearly_cents' => 27000, 'currency' => 'USD',
            'credits_per_month' => 150, 'active_products_limit' => 2, 'contact_limit' => 500,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    protected function subscribe(User $user, Plan $plan): void
    {
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth(),
        ]);
    }

    protected function fakeSuccessfulSearch(): void
    {
        Http::fake([
            'maps.googleapis.com/maps/api/place/textsearch/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'place_id' => 'place_1',
                    'name' => 'Bright Smile Dental',
                    'formatted_address' => '123 Main St, Austin, TX',
                    'rating' => 4.8,
                    'user_ratings_total' => 120,
                    'types' => ['dentist', 'point_of_interest', 'establishment'],
                ]],
            ], 200),
        ]);
    }

    public function test_visiting_leads_with_niche_and_location_in_the_query_string_searches_automatically(): void
    {
        config(['services.google_places.api_key' => 'test-key']);
        $user = User::factory()->create();
        $this->subscribe($user, $this->planWithGoogleMaps());
        $this->fakeSuccessfulSearch();

        $response = $this->actingAs($user)->get(route('leads.index', ['niche' => 'dentists', 'location' => 'Austin, TX']));

        $response->assertOk();
        $response->assertSee('Bright Smile Dental');
        $response->assertSee('Showing businesses matching your Offer Research recommendation');
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'dentists in Austin, TX') || str_contains((string) ($request['query'] ?? ''), 'dentists in Austin, TX'));
    }

    public function test_visiting_leads_without_query_params_does_not_search(): void
    {
        config(['services.google_places.api_key' => 'test-key']);
        $user = User::factory()->create();
        $this->subscribe($user, $this->planWithGoogleMaps());
        Http::fake();

        $response = $this->actingAs($user)->get(route('leads.index'));

        $response->assertOk();
        $response->assertDontSee('Showing businesses matching your Offer Research recommendation');
        Http::assertNothingSent();
    }

    public function test_auto_search_is_skipped_for_a_plan_without_the_google_maps_channel(): void
    {
        config(['services.google_places.api_key' => 'test-key']);
        $user = User::factory()->create();
        $this->subscribe($user, $this->planWithoutGoogleMaps());
        Http::fake();

        $response = $this->actingAs($user)->get(route('leads.index', ['niche' => 'dentists', 'location' => 'Austin, TX']));

        $response->assertOk();
        // Plain text in the view, not a Blade {{ }} expression, so it's
        // never HTML-entity-escaped on render — assertSee's default
        // $escape=true would otherwise turn this apostrophe into `&#039;`
        // before searching and never find it.
        $response->assertSee("isn't included in your current plan", false);
        Http::assertNothingSent();
    }

    public function test_auto_search_shows_a_friendly_error_instead_of_a_500_when_google_maps_isnt_configured(): void
    {
        config(['services.google_places.api_key' => null]);
        $user = User::factory()->create();
        $this->subscribe($user, $this->planWithGoogleMaps());
        Http::fake();

        $response = $this->actingAs($user)->get(route('leads.index', ['niche' => 'dentists', 'location' => 'Austin, TX']));

        $response->assertOk();
        $response->assertSee("Google Maps isn't configured yet");
        Http::assertNothingSent();
    }

    public function test_auto_search_marks_a_result_already_imported_into_the_crm(): void
    {
        config(['services.google_places.api_key' => 'test-key']);
        $user = User::factory()->create();
        $this->subscribe($user, $this->planWithGoogleMaps());
        $this->fakeSuccessfulSearch();
        CrmContact::create([
            'user_id' => $user->id, 'name' => 'Bright Smile Dental', 'source' => 'google_maps',
            'status' => 'new', 'raw_data' => ['google_place_id' => 'place_1'],
        ]);

        $response = $this->actingAs($user)->get(route('leads.index', ['niche' => 'dentists', 'location' => 'Austin, TX']));

        $response->assertOk();
        $response->assertSee('Already in CRM');
    }
}
