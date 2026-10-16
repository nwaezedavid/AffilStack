<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AddonInstall;
use App\Models\ApiToken;
use App\Models\Offer;
use App\Models\User;
use App\Services\Marketplace\AddonReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\MarketplaceTestHelpers;
use Tests\TestCase;

/**
 * Feature 7: "Connect with AffilStack" — a connected app asks for
 * permissions, the customer allows them, the app swaps the code for tokens
 * and calls the API within those permissions only.
 */
class AddonOAuthTest extends TestCase
{
    use MarketplaceTestHelpers;
    use RefreshDatabase;

    protected Addon $connectedApp;

    protected string $secret;

    protected const REDIRECT = 'https://rankbooster.example/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMarketplaceRoles();
        $this->connectedApp = $this->liveAddon($this->developer());
        $this->secret = $this->connectedApp->issueClientCredentials();
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function authorizeParams(array $extra = []): array
    {
        return array_merge([
            'response_type' => 'code',
            'client_id' => $this->connectedApp->client_id,
            'redirect_uri' => self::REDIRECT,
            'scope' => 'profile:read offers:read',
            'state' => 'xyz123',
        ], $extra);
    }

    protected function codeFrom(TestResponse $response): string
    {
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('xyz123', $query['state'] ?? null);

        return (string) $query['code'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function connect(User $customer, array $extra = [], array $tokenExtra = []): array
    {
        $code = $this->codeFrom($this->actingAs($customer)->post(route('oauth.approve'), $this->authorizeParams($extra)));

        return $this->postJson(route('api.oauth.token'), array_merge([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'client_id' => $this->connectedApp->client_id,
            'client_secret' => $this->secret,
        ], $tokenExtra))->assertOk()->json();
    }

    public function test_the_customer_sees_what_the_app_will_be_able_to_do(): void
    {
        $this->actingAs($this->customer())->get(route('oauth.authorize', $this->authorizeParams()))
            ->assertOk()
            ->assertSee('Connect Rank Booster to your AffilStack account')
            ->assertSee('See your name, email address and plan')
            ->assertSee('See your products and their research')
            ->assertDontSee('See your CRM contacts and lists')
            ->assertSee('rankbooster.example');
    }

    public function test_guests_sign_in_first(): void
    {
        $this->get(route('oauth.authorize', $this->authorizeParams()))->assertRedirect(route('login'));
    }

    public function test_an_unregistered_redirect_url_is_never_followed(): void
    {
        $this->actingAs($this->customer())->get(route('oauth.authorize', $this->authorizeParams(['redirect_uri' => 'https://evil.example/steal'])))
            ->assertOk()
            ->assertSee('has not registered');

        $this->actingAs($this->customer())->post(route('oauth.approve'), $this->authorizeParams(['redirect_uri' => 'https://evil.example/steal']))
            ->assertOk()
            ->assertSee('has not registered');

        $this->actingAs($this->customer())->get(route('oauth.authorize', $this->authorizeParams(['client_id' => 'afapp_unknown'])))
            ->assertSee('not registered with AffilStack');
    }

    public function test_asking_for_a_permission_the_app_does_not_list_is_refused(): void
    {
        $this->actingAs($this->customer())->get(route('oauth.authorize', $this->authorizeParams(['scope' => 'profile:read crm:write'])))
            ->assertRedirect(self::REDIRECT.'?error=invalid_scope&state=xyz123');
    }

    public function test_denying_sends_the_customer_back_with_an_error(): void
    {
        $this->actingAs($this->customer())->post(route('oauth.deny'), $this->authorizeParams())
            ->assertRedirect(self::REDIRECT.'?error=access_denied&state=xyz123');

        $this->assertSame(0, AddonInstall::count());
    }

    public function test_the_full_flow_gives_the_app_a_token_limited_to_the_allowed_permissions(): void
    {
        $customer = $this->customer();
        Offer::create(['user_id' => $customer->id, 'product_name' => 'Zebra Gizmo', 'product_url' => 'https://example.com/z', 'affiliate_network' => 'Awin', 'status' => 'ready']);

        $tokens = $this->connect($customer);

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertSame('profile:read offers:read', $tokens['scope']);
        $this->assertStringStartsWith('afat_', $tokens['access_token']);
        $this->assertStringStartsWith('afrt_', $tokens['refresh_token']);

        $install = AddonInstall::where('user_id', $customer->id)->sole();
        $this->assertSame(['profile:read', 'offers:read'], $install->scopes);
        $this->assertSame(1, $this->connectedApp->fresh()->installs_count);

        $auth = ['Authorization' => 'Bearer '.$tokens['access_token']];
        $this->getJson('/api/v1/offers', $auth)->assertOk()->assertSee('Zebra Gizmo');
        $this->getJson('/api/v1/me', $auth)->assertOk();

        // Not allowed: no permission, and endpoints apps can't use at all.
        $this->getJson('/api/v1/crm-contacts', $auth)->assertForbidden()->assertJson(['required_scope' => 'crm:read']);
        $this->postJson('/api/v1/offers', ['product_url' => 'https://example.com/new'], $auth)->assertForbidden();
        $this->getJson('/api/v1/admin/users', $auth)->assertForbidden();
        $this->getJson('/api/offers', $auth)->assertForbidden();

        // The customer can see and remove the connection.
        $this->actingAs($customer)->get(route('addons.show', $install))->assertOk()->assertSee('Connected.');
        $this->actingAs($customer)->delete(route('addons.destroy', $install));
        $this->getJson('/api/v1/offers', $auth)->assertUnauthorized();
    }

    public function test_pkce_is_checked_when_the_app_uses_it(): void
    {
        $verifier = str_repeat('v', 50);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $customer = $this->customer();

        $code = $this->codeFrom($this->actingAs($customer)->post(route('oauth.approve'), $this->authorizeParams(['code_challenge' => $challenge, 'code_challenge_method' => 'S256'])));
        $request = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT, 'client_id' => $this->connectedApp->client_id, 'client_secret' => $this->secret];

        $this->postJson(route('api.oauth.token'), $request + ['code_verifier' => 'wrong'])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
        $this->postJson(route('api.oauth.token'), $request + ['code_verifier' => $verifier])->assertOk();
    }

    public function test_a_wrong_client_secret_or_reused_code_is_refused(): void
    {
        $customer = $this->customer();
        $code = $this->codeFrom($this->actingAs($customer)->post(route('oauth.approve'), $this->authorizeParams()));
        $request = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT, 'client_id' => $this->connectedApp->client_id];

        $this->postJson(route('api.oauth.token'), $request + ['client_secret' => 'afsec_wrong'])->assertUnauthorized()->assertJson(['error' => 'invalid_client']);

        $tokens = $this->postJson(route('api.oauth.token'), $request + ['client_secret' => $this->secret])->assertOk()->json();

        // Used twice: someone else may have the code, so the app is signed out.
        $this->postJson(route('api.oauth.token'), $request + ['client_secret' => $this->secret])->assertStatus(400);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_client_credentials_can_be_sent_with_basic_auth(): void
    {
        $code = $this->codeFrom($this->actingAs($this->customer())->post(route('oauth.approve'), $this->authorizeParams()));

        $this->withHeader('Authorization', 'Basic '.base64_encode($this->connectedApp->client_id.':'.$this->secret))
            ->postJson(route('api.oauth.token'), ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT])
            ->assertOk();
    }

    public function test_refreshing_rotates_the_tokens(): void
    {
        $tokens = $this->connect($this->customer());
        $request = ['grant_type' => 'refresh_token', 'client_id' => $this->connectedApp->client_id, 'client_secret' => $this->secret];

        $new = $this->postJson(route('api.oauth.token'), $request + ['refresh_token' => $tokens['refresh_token']])->assertOk()->json();
        $this->assertNotSame($tokens['refresh_token'], $new['refresh_token']);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$new['access_token']])->assertOk();

        $this->postJson(route('api.oauth.token'), $request + ['refresh_token' => $tokens['refresh_token']])->assertStatus(400);
    }

    public function test_expired_access_tokens_stop_working(): void
    {
        $tokens = $this->connect($this->customer());
        ApiToken::where('type', 'app')->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_revoking_a_token(): void
    {
        $tokens = $this->connect($this->customer());

        $this->postJson(route('api.oauth.revoke'), ['token' => $tokens['access_token'], 'client_id' => $this->connectedApp->client_id, 'client_secret' => $this->secret])->assertOk();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    }

    public function test_a_suspended_app_or_developer_loses_access(): void
    {
        $tokens = $this->connect($this->customer());
        $auth = ['Authorization' => 'Bearer '.$tokens['access_token']];

        $this->connectedApp->update(['status' => 'suspended']);
        $this->getJson('/api/v1/me', $auth)->assertUnauthorized();

        $this->connectedApp->update(['status' => 'published']);
        $this->getJson('/api/v1/me', $auth)->assertOk();

        $this->connectedApp->developer->update(['status' => 'suspended']);
        $this->getJson('/api/v1/me', $auth)->assertUnauthorized();
        $this->postJson(route('api.oauth.token'), ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $this->connectedApp->client_id, 'client_secret' => $this->secret])
            ->assertUnauthorized();
    }

    public function test_only_the_developer_can_connect_an_app_that_is_not_approved_yet(): void
    {
        $developer = $this->developer(['slug' => 'indie']);
        $draft = app(AddonReviewService::class)->create($developer->developerProfile, 'app', 'Indie App');
        $draft->workingVersion->update(['redirect_uris' => [self::REDIRECT], 'scopes' => ['profile:read']]);
        $params = ['response_type' => 'code', 'client_id' => $draft->client_id, 'redirect_uri' => self::REDIRECT, 'state' => 'xyz123'];

        $this->actingAs($this->customer())->get(route('oauth.authorize', $params))->assertSee('not available on AffilStack right now');
        $this->actingAs($developer)->get(route('oauth.authorize', $params))->assertOk()->assertSee('Test mode');
        $this->codeFrom($this->actingAs($developer)->post(route('oauth.approve'), $params));
    }

    public function test_a_paid_app_asks_the_customer_to_subscribe_before_connecting(): void
    {
        $this->connectedApp->liveVersion->update(['pricing' => 'monthly', 'price_cents' => 1500]);
        $customer = $this->customer();

        $this->actingAs($customer)->get(route('oauth.authorize', $this->authorizeParams()))
            ->assertOk()
            ->assertSee('Subscribe — $15.00/month')
            ->assertDontSee('>Allow<', false);

        $this->actingAs($customer)->post(route('oauth.approve'), $this->authorizeParams())->assertRedirect();
        $this->assertSame('pending_payment', AddonInstall::where('user_id', $customer->id)->sole()->status);
        $this->assertSame(0, ApiToken::where('type', 'app')->count());
    }
}
