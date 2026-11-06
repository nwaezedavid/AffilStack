<?php

namespace Tests\Feature;

use App\Models\GoogleOauthSetting;
use App\Models\User;
use App\Support\Mobile\AppSignIn;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The sign-in pages shown inside the phone app leave out links to plans,
 * pricing and sign-up (App Store guideline 3.1.1 / 3.1.3, Google Play's
 * Payments policy) — see AppSignIn.
 */
class AppSignInModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function authorizeUrl(): string
    {
        return route('mobile.authorize', [
            'client_id' => 'affilstack-mobile',
            'redirect_uri' => 'affilstack://auth/callback',
            'response_type' => 'code',
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function test_the_app_sign_in_page_has_no_pricing_or_website_links(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect(route('login'));

        $this->assertTrue(AppSignIn::active());

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in with your AffilStack account to use the app.')
            ->assertDontSee('See plans')
            ->assertDontSee(route('registration.pricing'), false)
            ->assertDontSee('href="'.route('home').'"', false);
    }

    public function test_the_website_sign_in_page_still_offers_plans(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('See plans')
            ->assertSee(route('registration.pricing'), false);
    }

    public function test_app_sign_in_mode_lapses_after_half_an_hour(): void
    {
        $this->get($this->authorizeUrl());

        $this->travel(AppSignIn::MINUTES + 1)->minutes();

        $this->get(route('login'))->assertSee('See plans');
    }

    public function test_handing_the_code_to_the_app_ends_app_sign_in_mode(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->get($this->authorizeUrl());
        $this->assertTrue(AppSignIn::active());

        $this->actingAs($user)->post(route('mobile.authorize.approve'), [
            'client_id' => 'affilstack-mobile',
            'redirect_uri' => 'affilstack://auth/callback',
            'response_type' => 'code',
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);

        $this->assertFalse(AppSignIn::active());
    }

    public function test_google_sign_in_from_the_app_never_sends_an_unknown_email_to_pricing(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret'],
        ]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['sub' => 'google-sub-9', 'email' => 'nobody@example.com', 'email_verified' => true, 'name' => 'Nobody'], 200),
        ]);

        $this->get($this->authorizeUrl());
        $this->get(route('google.redirect'));

        $this->get(route('google.callback', ['state' => session('google_oauth_state'), 'code' => 'auth-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'No AffilStack account uses nobody@example.com. Sign in with the email address of your AffilStack account.');

        $this->assertGuest();
    }
}
