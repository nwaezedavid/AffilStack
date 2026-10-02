<?php

namespace Tests\Feature;

use App\Jobs\RunOfferResearch;
use App\Models\Offer;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The relaunch's core journey, step one: a paying customer lands on Get
 * Started, fills in the research form (with the 2026 affiliate platform
 * picker, including "Other"), and watches results appear via polling.
 */
class GetStartedDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_customer_sees_the_get_started_form_and_an_empty_results_area(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Research with AI')
            ->assertSee('Your research results will appear here')
            ->assertSee('data-research-form', false)
            ->assertSee('name="return_to" value="dashboard"', false);
    }

    public function test_the_platform_picker_lists_current_networks_grouped_with_an_other_choice(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('<optgroup label="Major affiliate networks">', false)
            ->assertSee('<optgroup label="Africa">', false)
            ->assertSee('Impact.com')
            ->assertSee('Expertnaire')
            ->assertSee('PartnerStack')
            ->assertSee('Digistore24')
            ->assertSee('Direct / in-house programme')
            ->assertSee('value="__other"', false)
            ->assertSee('name="affiliate_network_other"', false)
            // ShareASale was folded into Awin — it's only a search alias now.
            ->assertDontSee('<option value="ShareASale"', false)
            ->assertSee('data-aliases="shareasale share a sale"', false);
    }

    public function test_choosing_other_stores_the_typed_platform_name(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $this->actingAs($user)->post(route('offers.store'), [
            'product_name' => 'Acme Course',
            'product_url' => 'https://acme.example',
            'affiliate_network' => '__other',
            'affiliate_network_other' => 'Acme Partners Club',
            'return_to' => 'dashboard',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('offers', ['user_id' => $user->id, 'affiliate_network' => 'Acme Partners Club', 'status' => 'queued']);
        Queue::assertPushed(RunOfferResearch::class);
    }

    public function test_choosing_other_without_typing_a_name_is_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $this->actingAs($user)->post(route('offers.store'), [
            'product_name' => 'Acme Course',
            'product_url' => 'https://acme.example',
            'affiliate_network' => '__other',
        ])->assertSessionHasErrors('affiliate_network_other');

        $this->assertDatabaseMissing('offers', ['user_id' => $user->id]);
    }

    public function test_a_previously_stored_unlisted_network_shows_as_other_on_the_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['_old_input' => ['affiliate_network' => 'ShareASale']])
            ->get(route('offers.create'))
            ->assertOk()
            ->assertSee('<option value="__other" selected>', false)
            ->assertSee('value="ShareASale"', false);
    }

    public function test_a_json_submission_returns_the_status_url_to_poll(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);

        $response = $this->actingAs($user)->postJson(route('offers.store'), [
            'product_name' => 'Acme Course',
            'product_url' => 'https://acme.example',
            'affiliate_network' => 'Selar',
        ]);

        $offer = Offer::where('user_id', $user->id)->firstOrFail();
        $response->assertCreated()
            ->assertJson(['offer_id' => $offer->id, 'status' => 'queued', 'status_url' => route('offers.research.status', $offer)]);
    }

    public function test_a_json_submission_without_credits_returns_a_clear_error(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 0]);

        $this->actingAs($user)->postJson(route('offers.store'), [
            'product_name' => 'Acme Course',
            'product_url' => 'https://acme.example',
            'affiliate_network' => 'Selar',
        ])->assertStatus(402)->assertJsonFragment(['message' => 'Not enough credits for offer research. Upgrade your plan or buy a credit top-up.']);
    }

    public function test_research_status_shows_progress_then_the_finished_results(): void
    {
        $user = User::factory()->create();
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Acme Course', 'product_url' => 'https://acme.example',
            'affiliate_network' => 'Selar', 'status' => 'queued',
        ]);

        $this->actingAs($user)->getJson(route('offers.research.status', $offer))
            ->assertOk()
            ->assertJson(['status' => 'queued'])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Our AI is researching Acme Course'));

        $offer->update([
            'status' => 'ready',
            'recommended_channel' => 'ugc',
            'recommended_angle' => 'Learn it in a weekend.',
            'research_data' => ['product_overview' => 'An online course that teaches funnels.'],
        ]);

        $this->actingAs($user)->getJson(route('offers.research.status', $offer))
            ->assertOk()
            ->assertJson(['status' => 'ready'])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'An online course that teaches funnels.')
                && str_contains($html, route('offers.feature', [$offer, 'ugc'])));
    }

    public function test_research_status_is_private_to_the_offers_owner(): void
    {
        $owner = User::factory()->create();
        $offer = Offer::create([
            'user_id' => $owner->id, 'product_name' => 'Acme', 'product_url' => 'https://acme.example',
            'affiliate_network' => 'Selar', 'status' => 'queued',
        ]);

        $this->actingAs(User::factory()->create())->getJson(route('offers.research.status', $offer))->assertForbidden();
    }

    public function test_failed_research_shows_a_retry_that_requeues_the_same_offer(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Acme', 'product_url' => 'https://acme.example',
            'affiliate_network' => 'Selar', 'status' => 'failed',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee("The research didn't finish", false)
            ->assertSee(route('offers.research.retry', $offer), false);

        $this->actingAs($user)->postJson(route('offers.research.retry', $offer))
            ->assertOk()->assertJson(['status' => 'queued']);

        $this->assertSame('queued', $offer->fresh()->status);
        Queue::assertPushed(RunOfferResearch::class, fn (RunOfferResearch $job) => $job->offer->is($offer));
    }

    public function test_the_dashboard_shows_the_latest_products_results(): void
    {
        $user = User::factory()->create();
        Offer::create(['user_id' => $user->id, 'product_name' => 'Older Product', 'product_url' => 'https://a.example', 'affiliate_network' => 'Awin', 'status' => 'ready'])
            ->forceFill(['created_at' => now()->subDay()])->save();
        Offer::create([
            'user_id' => $user->id, 'product_name' => 'Newest Product', 'product_url' => 'https://b.example', 'affiliate_network' => 'Selar',
            'status' => 'ready', 'recommended_channel' => 'blog', 'research_data' => ['product_overview' => 'Newest overview text.'],
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Newest overview text.')
            ->assertSee('Your other products')
            ->assertSee('Older Product');
    }

    public function test_a_newly_paid_customer_lands_on_get_started_with_the_tour_ready(): void
    {
        Notification::fake();
        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/x']])]);

        $this->post(route('registration.store', $plan), [
            'name' => 'Ada Seller', 'email' => 'ada@example.com',
            'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly', 'accepts_refund_policy' => '1',
        ]);

        $pending = PendingSignup::where('email', 'ada@example.com')->firstOrFail();
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();
        Http::fake(['api.flutterwave.com/v3/transactions/*' => Http::response(['status' => 'success', 'data' => [
            'id' => 4242, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful', 'amount' => 67.00, 'currency' => 'USD',
            'customer' => ['email' => 'ada@example.com'],
            'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
        ]])]);

        $this->get(route('registration.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => 4242, 'status' => 'successful']))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Get Started')
            ->assertSee('Research with AI')
            ->assertSee('autoStart: true', false);
    }
}
