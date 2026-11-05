<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\MobileAuthorizationCode;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileAuthServer;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sign-in for the official phone apps: /mobile/authorize on the website,
 * then authorization code + PKCE and rotating refresh tokens on
 * /api/oauth/token (see MobileAuthServer).
 */
class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    protected const REDIRECT = 'affilstack://auth/callback';

    protected string $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->verifier = str_repeat('verifier-', 6).'abc';
    }

    protected function challenge(?string $verifier = null): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier ?? $this->verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, string>
     */
    protected function authorizeParams(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'affilstack-mobile',
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'state' => 'xyz123',
            'code_challenge' => $this->challenge(),
            'code_challenge_method' => 'S256',
        ], $overrides);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    /**
     * Signs $user in on the website and returns the one-time code the app
     * would receive.
     */
    protected function codeFor(User $user): string
    {
        $location = $this->actingAs($user)->post(route('mobile.authorize.approve'), $this->authorizeParams())->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        return (string) $query['code'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchange(string $code, array $overrides = []): array
    {
        return $this->postJson('/api/oauth/token', array_merge([
            'grant_type' => 'authorization_code',
            'client_id' => 'affilstack-mobile',
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $this->verifier,
            'device_name' => 'David’s iPhone',
            'platform' => 'ios',
            'app_version' => '1.0.0',
        ], $overrides))->json();
    }

    public function test_guests_are_sent_to_the_website_sign_in_first(): void
    {
        $this->get(route('mobile.authorize', $this->authorizeParams()))->assertRedirect(route('login'));
    }

    public function test_a_signed_in_customer_is_asked_to_confirm(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('mobile.authorize', $this->authorizeParams()))
            ->assertOk()
            ->assertSee('Continue to the')
            ->assertSee($user->email);
    }

    public function test_an_unregistered_redirect_address_is_never_followed(): void
    {
        $this->actingAs($this->customer())->get(route('mobile.authorize', $this->authorizeParams(['redirect_uri' => 'https://evil.example/cb'])))
            ->assertOk()
            ->assertSee('does not recognise');

        $this->actingAs($this->customer())->post(route('mobile.authorize.approve'), $this->authorizeParams(['redirect_uri' => 'https://evil.example/cb']))
            ->assertOk();

        $this->assertDatabaseCount('mobile_authorization_codes', 0);
    }

    public function test_pkce_with_s256_is_required(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('mobile.authorize', $this->authorizeParams(['code_challenge_method' => 'plain'])))
            ->assertRedirect(self::REDIRECT.'?error=invalid_request&state=xyz123');

        $params = $this->authorizeParams();
        unset($params['code_challenge']);

        $this->actingAs($user)->get(route('mobile.authorize', $params))
            ->assertRedirect(self::REDIRECT.'?error=invalid_request&state=xyz123');
    }

    public function test_continue_sends_the_app_a_code_and_cancel_sends_an_error(): void
    {
        $user = $this->customer();

        $location = $this->actingAs($user)->post(route('mobile.authorize.approve'), $this->authorizeParams())->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT.'?code=', $location);
        $this->assertStringContainsString('state=xyz123', $location);
        $this->assertDatabaseCount('mobile_authorization_codes', 1);

        $this->actingAs($user)->post(route('mobile.authorize.deny'), $this->authorizeParams())
            ->assertRedirect(self::REDIRECT.'?error=access_denied&state=xyz123');
    }

    public function test_suspended_and_partner_only_accounts_are_turned_away(): void
    {
        $suspended = $this->customer(['is_suspended' => true]);
        $partner = $this->customer(['is_affiliate_only' => true]);

        $this->actingAs($partner)->get(route('mobile.authorize', $this->authorizeParams()))
            ->assertOk()
            ->assertSee('Partner Portal');

        $this->actingAs($partner)->post(route('mobile.authorize.approve'), $this->authorizeParams())->assertOk();

        $this->assertDatabaseCount('mobile_authorization_codes', 0);

        // The website itself signs suspended accounts out; the token
        // endpoint refuses them too even if a code exists.
        $code = app(MobileAuthServer::class)->issueCode($suspended, self::REDIRECT, $this->challenge());
        $this->assertSame('invalid_grant', $this->exchange($code)['error']);
    }

    public function test_the_code_is_swapped_for_tokens_and_a_device(): void
    {
        $user = $this->customer();
        $body = $this->exchange($this->codeFor($user));

        $this->assertStringStartsWith('afmt_', $body['access_token']);
        $this->assertStringStartsWith('afmr_', $body['refresh_token']);
        $this->assertSame(3600, $body['expires_in']);

        $device = MobileDevice::sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('ios', $device->platform);
        $this->assertSame('David’s iPhone', $device->name);

        $token = ApiToken::sole();
        $this->assertSame('mobile', $token->type);
        $this->assertSame($device->id, $token->mobile_device_id);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$body['access_token']])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_a_wrong_verifier_or_redirect_is_refused(): void
    {
        $user = $this->customer();

        $this->assertSame('invalid_grant', $this->exchange($this->codeFor($user), ['code_verifier' => str_repeat('x', 50)])['error']);
        $this->assertSame('invalid_grant', $this->exchange($this->codeFor($user), ['redirect_uri' => 'affilstack://other'])['error']);
        $this->assertDatabaseCount('mobile_devices', 0);
    }

    public function test_a_code_works_once_and_expires(): void
    {
        $user = $this->customer();
        $code = $this->codeFor($user);

        $this->assertArrayHasKey('access_token', $this->exchange($code));
        $this->assertSame('invalid_grant', $this->exchange($code)['error']);

        $expired = $this->codeFor($user);
        MobileAuthorizationCode::query()->latest('id')->first()->update(['expires_at' => now()->subMinute()]);
        $this->assertSame('invalid_grant', $this->exchange($expired)['error']);
    }

    public function test_refresh_tokens_rotate_and_a_replayed_one_signs_the_device_out(): void
    {
        $user = $this->customer();
        $first = $this->exchange($this->codeFor($user));

        $second = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'affilstack-mobile',
            'refresh_token' => $first['refresh_token'],
        ])->assertOk()->json();

        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);
        // The old access token is replaced by the new one.
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$first['access_token']])->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$second['access_token']])->assertOk();

        // Someone replays the first refresh token: the device is signed out.
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'affilstack-mobile',
            'refresh_token' => $first['refresh_token'],
        ])->assertStatus(400);

        $this->assertNotNull(MobileDevice::sole()->revoked_at);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$second['access_token']])->assertUnauthorized();
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'affilstack-mobile',
            'refresh_token' => $second['refresh_token'],
        ])->assertStatus(400);
    }

    public function test_a_suspended_account_cannot_refresh(): void
    {
        $user = $this->customer();
        $tokens = $this->exchange($this->codeFor($user));

        $user->update(['is_suspended' => true]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertForbidden();
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'affilstack-mobile',
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400);
    }

    public function test_an_expired_access_token_is_rejected(): void
    {
        $tokens = $this->exchange($this->codeFor($this->customer()));

        $this->travel(61)->minutes();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_revoking_a_token_signs_the_device_out(): void
    {
        $tokens = $this->exchange($this->codeFor($this->customer()));

        $this->postJson('/api/oauth/revoke', ['client_id' => 'affilstack-mobile', 'token' => $tokens['refresh_token']])->assertOk();

        $this->assertNotNull(MobileDevice::sole()->revoked_at);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_changing_the_password_signs_every_phone_out(): void
    {
        $user = $this->customer();
        $tokens = $this->exchange($this->codeFor($user));

        $user->forceFill(['password' => Hash::make('a-brand-new-password-123')])->save();

        $this->assertNotNull(MobileDevice::sole()->revoked_at);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_connected_apps_without_a_secret_are_still_refused(): void
    {
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'some-other-app',
            'code' => 'abc',
        ])->assertStatus(401)->assertJsonPath('error', 'invalid_client');
    }
}
