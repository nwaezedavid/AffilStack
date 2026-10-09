<?php

namespace Tests\Feature;

use App\Filament\Pages\AiEngineSettings;
use App\Models\ApiToken;
use App\Models\Earning;
use App\Models\GoogleOauthSetting;
use App\Models\Offer;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Notifications\EmailAddressChanged;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Regression tests for the pre-relaunch security review (October 2026):
 * Google sign-in account takeover paths, email changes, an earnings IDOR,
 * super-admin-only AI settings, panel MFA on admin routes, and team seats
 * creating products through the API.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_google_sign_in_is_refused_when_the_account_is_linked_to_another_google_identity(): void
    {
        $this->enableGoogleLogin();
        $user = $this->customer(['email' => 'ada@example.com', 'google_id' => 'ada-sub']);

        $this->fakeGoogleProfile('someone-else-sub', 'ada@example.com');

        $this->googleCallback()->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('ada-sub', $user->fresh()->google_id);
    }

    public function test_google_sign_in_still_asks_for_the_two_factor_code(): void
    {
        $this->enableGoogleLogin();
        $user = $this->customer(['email' => 'ada@example.com', 'google_id' => 'ada-sub']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        $this->fakeGoogleProfile('ada-sub', 'ada@example.com');

        $this->googleCallback()->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_first_google_sign_in_retires_api_tokens_made_before_ownership_was_proven(): void
    {
        $this->enableGoogleLogin();
        $user = $this->customer(['email' => 'ada@example.com', 'google_id' => null]);
        ApiToken::generate($user, 'made before Google proved the email');

        $this->fakeGoogleProfile('ada-sub', 'ada@example.com');

        $this->googleCallback()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame(0, $user->apiTokens()->count());
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $user = $this->customer(['email' => 'old@example.com', 'password' => Hash::make('right-password')]);

        $this->actingAs($user)->put('/profile/personal', ['name' => 'Ada', 'email' => 'new@example.com'])
            ->assertSessionHasErrors('current_password', null, 'personal');
        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->actingAs($user)->put('/profile/personal', ['name' => 'Ada', 'email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password', null, 'personal');

        Notification::fake();

        $this->actingAs($user)->put('/profile/personal', ['name' => 'Ada', 'email' => 'new@example.com', 'current_password' => 'right-password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);

        Notification::assertSentOnDemand(EmailAddressChanged::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'old@example.com');
    }

    public function test_other_profile_fields_save_without_a_password(): void
    {
        $user = $this->customer(['email' => 'ada@example.com']);

        $this->actingAs($user)->put('/profile/personal', ['name' => 'Ada Okafor', 'email' => 'ada@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Ada Okafor', $user->fresh()->name);
    }

    public function test_a_manual_earning_cannot_be_attached_to_another_accounts_offer(): void
    {
        $victim = $this->customer();
        $victimOffer = $this->offerFor($victim, 'Victim Secret Product');
        $attacker = $this->customer();

        $this->actingAs($attacker)->post('/earnings', [
            'network' => 'shareasale',
            'amount' => 9999,
            'status' => 'paid',
            'converted_at' => now()->toDateString(),
            'offer_id' => $victimOffer->id,
        ])->assertRedirect();

        $this->assertNull(Earning::where('user_id', $attacker->id)->sole()->offer_id);
        $this->actingAs($attacker)->get('/earnings')->assertOk()->assertDontSee('Victim Secret Product');
        $this->actingAs($victim)->get('/earnings')->assertOk()->assertDontSee('9,999');
    }

    public function test_a_single_product_team_seat_cannot_create_products_through_the_api(): void
    {
        $owner = $this->customer(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $seat = $this->customer(['agency_owner_id' => $owner->id, 'seat_offer_id' => $this->offerFor($owner, 'Assigned')->id]);
        $token = ApiToken::generate($seat, 'seat')['plainText'];

        $this->postJson('/api/v1/offers', [
            'product_name' => 'Seat product', 'product_url' => 'https://y.example', 'affiliate_network' => 'ShareASale',
        ], ['Authorization' => "Bearer {$token}"])->assertForbidden();

        $this->assertFalse(Offer::where('product_name', 'Seat product')->exists());
    }

    public function test_the_ai_engine_page_is_for_the_super_admin_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->assertFalse(AiEngineSettings::canAccess());

        $owner = User::factory()->create();
        $owner->assignRole(['admin', 'super-admin']);
        $this->actingAs($owner);
        $this->assertTrue(AiEngineSettings::canAccess());
    }

    public function test_admin_google_analytics_connect_requires_the_panel_sign_in(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin/google-site-analytics/connect');

        $this->assertGuest();
    }

    public function test_each_rate_limited_route_has_its_own_budget(): void
    {
        // Six other throttled requests (the contact form allows five a
        // minute) must not use up the contact form's own allowance.
        foreach (range(1, 6) as $i) {
            $this->post('/affiliate/set-password/999999', []);
        }

        $this->post('/contact', [])->assertStatus(302);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    protected function offerFor(User $user, string $name): Offer
    {
        return Offer::create([
            'user_id' => $user->id,
            'product_name' => $name,
            'product_url' => 'https://example.com/'.uniqid(),
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);
    }

    protected function enableGoogleLogin(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'x.apps.googleusercontent.com', 'client_secret' => 's'],
        ]);
    }

    protected function fakeGoogleProfile(string $sub, string $email): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a'], 200),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Ada',
            ], 200),
        ]);
    }

    protected function googleCallback(): TestResponse
    {
        $this->get(route('google.redirect'));

        return $this->get(route('google.callback', ['state' => session('google_oauth_state'), 'code' => 'c']));
    }
}
