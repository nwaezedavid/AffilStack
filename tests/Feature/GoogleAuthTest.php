<?php

namespace Tests\Feature;

use App\Models\GoogleOauthSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Continue with Google" (task #84): one callback, two starting points
 * (login page vs. a plan's signup form), no free registration either way
 * — see GoogleAuthController's class docblock for the full decision.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret'],
        ]);
    }

    protected function fakeGoogleProfile(array $overrides = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(array_merge([
                'sub' => 'google-sub-123',
                'email' => 'jane@example.com',
                'email_verified' => true,
                'name' => 'Jane Marketer',
            ], $overrides), 200),
        ]);
    }

    public function test_login_intent_signs_in_an_existing_user_and_links_google_id(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'google_id' => null]);
        $user->assignRole('user');

        $this->fakeGoogleProfile();

        $redirect = $this->get(route('google.redirect'));
        $state = session('google_oauth_state');
        $this->assertNotEmpty($state);

        $callback = $this->get(route('google.callback', ['state' => $state, 'code' => 'auth-code']));

        $callback->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-sub-123', $user->fresh()->google_id);
    }

    public function test_login_intent_with_no_matching_account_sends_visitor_to_pricing(): void
    {
        $this->fakeGoogleProfile(['email' => 'nobody@example.com']);

        $this->get(route('google.redirect'));
        $state = session('google_oauth_state');

        $callback = $this->get(route('google.callback', ['state' => $state, 'code' => 'auth-code']));

        $callback->assertRedirect(route('registration.pricing'));
        $this->assertGuest();
    }

    public function test_signup_intent_creates_no_account_until_payment_verifies(): void
    {
        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->fakeGoogleProfile();
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/pay/fake-link'],
            ], 200),
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-sub-123', 'email' => 'jane@example.com',
                'email_verified' => true, 'name' => 'Jane Marketer',
            ], 200),
        ]);

        $this->post(route('registration.google', $plan), ['billing_cycle' => 'monthly']);
        $state = session('google_oauth_state');

        $callback = $this->get(route('google.callback', ['state' => $state, 'code' => 'auth-code']));

        $callback->assertRedirect('https://checkout.flutterwave.com/pay/fake-link');
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $pending = PendingSignup::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('google-sub-123', $pending->google_id);
        $this->assertSame($plan->id, $pending->plan_id);
    }

    public function test_signup_intent_logs_in_directly_when_the_email_already_has_an_account(): void
    {
        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $user->assignRole('user');

        $this->fakeGoogleProfile();

        $this->post(route('registration.google', $plan), ['billing_cycle' => 'monthly']);
        $state = session('google_oauth_state');

        $callback = $this->get(route('google.callback', ['state' => $state, 'code' => 'auth-code']));

        $callback->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('pending_signups', 0);
    }

    public function test_a_tampered_state_is_rejected(): void
    {
        $this->fakeGoogleProfile();

        $this->get(route('google.redirect'));

        $callback = $this->get(route('google.callback', ['state' => 'not-the-real-state', 'code' => 'auth-code']));

        $callback->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
