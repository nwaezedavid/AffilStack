<?php

namespace Tests\Feature;

use App\Models\AppleOauthSetting;
use App\Models\User;
use App\Services\Auth\AppleSignInService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Continue with Apple" — sign-in only, linked by Apple ID or verified
 * email, with Apple's cross-site POST handed over to a same-site finish
 * step (see AppleAuthController).
 */
class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $this->privateKey = $pem;

        AppleOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => [
                'services_id' => 'com.affilstack.signin',
                'team_id' => 'TEAMID1234',
                'key_id' => 'KEYID12345',
                'private_key' => $this->privateKey,
            ],
        ]);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    protected function jwt(array $claims): string
    {
        $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'kid' => 'test']).'.'.$encode($claims).'.signature';
    }

    /**
     * Runs the whole browser round trip and returns the final response.
     */
    protected function signInWithApple(array $claims = [], ?string $postedState = null, string $refreshToken = 'apple-refresh-token'): TestResponse
    {
        $this->get(route('apple.redirect'))->assertRedirect();

        $state = session('apple_oauth_state');
        $nonce = session('apple_oauth_nonce');

        Http::fake([
            'appleid.apple.com/auth/token' => Http::response([
                'id_token' => $this->jwt(array_merge([
                    'iss' => 'https://appleid.apple.com',
                    'aud' => 'com.affilstack.signin',
                    'exp' => time() + 600,
                    'iat' => time(),
                    'sub' => 'apple-sub-001',
                    'email' => 'jane@example.com',
                    'email_verified' => 'true',
                    'nonce' => hash('sha256', (string) $nonce),
                ], $claims)),
                'refresh_token' => $refreshToken,
            ]),
            'appleid.apple.com/auth/revoke' => Http::response('', 200),
        ]);

        $location = $this->post(route('apple.callback'), ['state' => $postedState ?? $state, 'code' => 'apple-code'])
            ->assertStatus(303)
            ->headers->get('Location');

        $this->assertStringStartsWith(route('apple.finish'), (string) $location);

        return $this->get((string) $location);
    }

    public function test_the_button_sends_the_visitor_to_apple_with_a_hashed_nonce(): void
    {
        $location = $this->get(route('apple.redirect'))->headers->get('Location');

        $this->assertStringStartsWith('https://appleid.apple.com/auth/authorize?', (string) $location);
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertSame('com.affilstack.signin', $query['client_id']);
        $this->assertSame('form_post', $query['response_mode']);
        $this->assertSame('name email', $query['scope']);
        $this->assertSame(route('apple.callback'), $query['redirect_uri']);
        $this->assertSame(session('apple_oauth_state'), $query['state']);
        $this->assertSame(hash('sha256', session('apple_oauth_nonce')), $query['nonce']);
    }

    public function test_the_login_page_shows_the_apple_button_only_when_enabled(): void
    {
        $this->get(route('login'))->assertSee('Continue with Apple');

        AppleOauthSetting::current()->update(['is_enabled' => false]);

        $this->get(route('login'))->assertDontSee('Continue with Apple');
        $this->get(route('apple.redirect'))->assertRedirect(route('login'));
    }

    public function test_a_verified_email_links_the_account_and_retires_the_old_password(): void
    {
        $user = $this->customer(['email' => 'jane@example.com', 'password' => Hash::make('old-password-123')]);

        $this->signInWithApple()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('apple-sub-001', $user->apple_id);
        $this->assertSame('apple-refresh-token', $user->apple_refresh_token);
        $this->assertFalse(Hash::check('old-password-123', $user->password));
    }

    public function test_a_returning_apple_id_signs_in_even_with_a_hidden_email(): void
    {
        $user = $this->customer(['email' => 'jane@example.com', 'apple_id' => 'apple-sub-001', 'password' => Hash::make('keep-me-123')]);

        $this->signInWithApple(['email' => 'x1y2@privaterelay.appleid.com', 'is_private_email' => 'true'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('keep-me-123', $user->fresh()->password));
    }

    public function test_it_returns_to_the_page_that_asked_for_sign_in(): void
    {
        $this->customer(['email' => 'jane@example.com']);
        $intended = route('mobile.authorize', ['client_id' => 'affilstack-mobile', 'redirect_uri' => 'affilstack://auth/callback', 'response_type' => 'code']);

        $this->get($intended)->assertRedirect(route('login'));

        $this->signInWithApple()->assertRedirect($intended);
    }

    public function test_an_unknown_email_never_creates_an_account_or_sends_to_pricing(): void
    {
        $this->signInWithApple(['email' => 'stranger@example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'No AffilStack account uses the email on this Apple ID. Sign in with your email and password (or Google) instead.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);
    }

    public function test_an_unmatched_hidden_email_gets_its_own_explanation(): void
    {
        $this->customer(['email' => 'jane@example.com']);

        $this->signInWithApple(['email' => 'x1y2@privaterelay.appleid.com', 'is_private_email' => true])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'hides your email'));

        $this->assertGuest();
    }

    public function test_an_unverified_email_is_never_used_to_link(): void
    {
        $this->customer(['email' => 'jane@example.com']);

        $this->signInWithApple(['email_verified' => 'false'])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_account_linked_to_another_apple_id_is_refused(): void
    {
        $this->customer(['email' => 'jane@example.com', 'apple_id' => 'someone-else']);

        $this->signInWithApple()->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_a_forged_state_is_refused(): void
    {
        $this->customer(['email' => 'jane@example.com']);

        $this->signInWithApple([], postedState: 'forged-state')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_finish_link_without_a_parked_answer_is_refused(): void
    {
        $this->get(route('apple.redirect'));

        $this->get(route('apple.finish', ['key' => 'made-up']))->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_id_tokens_for_another_app_issuer_nonce_or_time_are_refused(): void
    {
        $this->customer(['email' => 'jane@example.com']);

        foreach ([['aud' => 'com.someone.else'], ['iss' => 'https://evil.example'], ['nonce' => 'wrong'], ['exp' => time() - 3600]] as $claims) {
            $this->signInWithApple($claims)->assertRedirect(route('login'))->assertSessionHas('error');
            $this->assertGuest();
        }
    }

    public function test_cancelling_on_apple_just_returns_to_the_login_page(): void
    {
        $this->get(route('apple.redirect'));

        $location = $this->post(route('apple.callback'), ['error' => 'user_cancelled_authorize', 'state' => session('apple_oauth_state')])->headers->get('Location');

        $this->get((string) $location)->assertRedirect(route('login'))->assertSessionMissing('error');
    }

    public function test_two_factor_accounts_still_face_the_challenge(): void
    {
        $this->customer([
            'email' => 'jane@example.com',
            'apple_id' => 'apple-sub-001',
            'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->signInWithApple()->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_the_client_secret_is_a_valid_es256_token_signed_with_the_saved_key(): void
    {
        $jwt = app(AppleSignInService::class)->clientSecret();
        [$header, $payload, $signature] = explode('.', $jwt);
        $decode = fn (string $part): string => (string) base64_decode(strtr($part, '-_', '+/'));

        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEYID12345'], json_decode($decode($header), true));
        $claims = json_decode($decode($payload), true);
        $this->assertSame('TEAMID1234', $claims['iss']);
        $this->assertSame('com.affilstack.signin', $claims['sub']);
        $this->assertSame('https://appleid.apple.com', $claims['aud']);

        $raw = $decode($signature);
        $this->assertSame(64, strlen($raw));

        $integer = function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");

            if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".chr(strlen($bytes)).$bytes;
        };
        $der = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));
        $der = "\x30".chr(strlen($der)).$der;

        $public = openssl_pkey_get_details(openssl_pkey_get_private($this->privateKey))['key'];
        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $der, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_deleting_the_account_unlinks_it_from_apple(): void
    {
        $user = $this->customer(['password' => Hash::make('correct-horse-battery'), 'apple_id' => 'apple-sub-001']);
        $user->forceFill(['apple_refresh_token' => 'apple-refresh-token'])->save();

        Http::fake(['appleid.apple.com/auth/revoke' => Http::response('', 200)]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'correct-horse-battery'])->assertRedirect(route('login'));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://appleid.apple.com/auth/revoke'
            && $request['token'] === 'apple-refresh-token'
            && $request['client_id'] === 'com.affilstack.signin');
        $this->assertSoftDeleted($user);
    }
}
