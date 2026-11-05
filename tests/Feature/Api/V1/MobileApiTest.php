<?php

namespace Tests\Feature\Api\V1;

use App\Models\AgentActivity;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\MobileDevice;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * /api/v1/mobile/* — the endpoints only the official phone app uses.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    /**
     * A signed-in phone for $user and the bearer headers its app sends.
     *
     * @return array{0: MobileDevice, 1: array<string, string>}
     */
    protected function signedInPhone(User $user, array $deviceAttributes = []): array
    {
        $device = MobileDevice::factory()->for($user)->create($deviceAttributes);
        $plain = 'afmt_'.Str::random(48);

        ApiToken::create([
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'type' => 'mobile',
            'scope' => 'full',
            'name' => 'AffilStack app',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return [$device, ['Authorization' => 'Bearer '.$plain]];
    }

    public function test_config_is_public_and_reports_versions_and_the_banner(): void
    {
        SiteSetting::set('mobile_app_min_version_ios', '1.2.0');
        SiteSetting::set('mobile_app_latest_version', '1.3.0');
        SiteSetting::setFlag('mobile_app_announcement_enabled', true);
        SiteSetting::set('mobile_app_announcement_message', 'Maintenance tonight.');
        SiteSetting::set('mobile_app_announcement_level', 'warning');

        $this->getJson('/api/v1/mobile/config')
            ->assertOk()
            ->assertJsonPath('versions.ios.minimum', '1.2.0')
            ->assertJsonPath('versions.android.minimum', '1.0.0')
            ->assertJsonPath('versions.ios.latest', '1.3.0')
            ->assertJsonPath('announcement.message', 'Maintenance tonight.')
            ->assertJsonPath('announcement.level', 'warning')
            ->assertJsonPath('auth.client_id', 'affilstack-mobile')
            ->assertJsonPath('features.in_app_purchases', false);
    }

    public function test_the_banner_is_hidden_when_switched_off(): void
    {
        SiteSetting::set('mobile_app_announcement_message', 'Old news');

        $this->getJson('/api/v1/mobile/config')->assertOk()->assertJsonPath('announcement', null);
    }

    public function test_home_returns_the_account_wallet_progress_and_approvals(): void
    {
        $user = $this->customer(['credits_balance' => 420]);
        [, $headers] = $this->signedInPhone($user);

        AgentActivity::create(['user_id' => $user->id, 'agent' => 'reply_copilot', 'action' => 'reply', 'summary' => 'Reply to Ada', 'status' => AgentActivity::STATUS_NEEDS_APPROVAL]);

        $this->getJson('/api/v1/mobile/home', $headers)
            ->assertOk()
            ->assertJsonPath('account.email', $user->email)
            ->assertJsonPath('account.credits_balance', 420)
            ->assertJsonPath('agents.pending_approvals', 1)
            ->assertJsonStructure(['wallet' => ['balance_minor', 'currency', 'formatted'], 'progress' => ['tiles']]);
    }

    public function test_developer_api_tokens_cannot_use_the_app_endpoints(): void
    {
        $user = $this->customer();
        $plain = ApiToken::generate($user, 'Zapier')['plainText'];

        $this->getJson('/api/v1/mobile/home', ['Authorization' => "Bearer {$plain}"])->assertForbidden();
        $this->getJson('/api/v1/mobile/home')->assertUnauthorized();
    }

    public function test_the_phone_reports_its_push_token_and_a_moved_token_leaves_the_old_row(): void
    {
        $user = $this->customer();
        [$old] = $this->signedInPhone($this->customer(), ['push_token' => 'ExponentPushToken[abc]', 'push_provider' => 'expo']);
        [$device, $headers] = $this->signedInPhone($user);

        $this->putJson('/api/v1/mobile/devices/current', [
            'app_version' => '1.1.0',
            'locale' => 'fr',
            'push_provider' => 'expo',
            'push_token' => 'ExponentPushToken[abc]',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.has_push', true)
            ->assertJsonPath('data.is_current', true);

        $this->assertSame('ExponentPushToken[abc]', $device->fresh()->push_token);
        $this->assertSame('fr', $device->fresh()->locale);
        $this->assertNull($old->fresh()->push_token);
    }

    public function test_listing_and_signing_out_another_phone(): void
    {
        $user = $this->customer();
        [$current, $headers] = $this->signedInPhone($user);
        [$other, $otherHeaders] = $this->signedInPhone($user);
        [$stranger] = $this->signedInPhone($this->customer());

        $this->getJson('/api/v1/mobile/devices', $headers)->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/mobile/devices/{$stranger->id}", [], $headers)->assertNotFound();
        $this->deleteJson("/api/v1/mobile/devices/{$other->id}", [], $headers)->assertOk();

        $this->assertNotNull($other->fresh()->revoked_at);
        $this->getJson('/api/v1/mobile/home', $otherHeaders)->assertUnauthorized();
        $this->getJson('/api/v1/mobile/home', $headers)->assertOk();
        $this->assertNull($current->fresh()->revoked_at);
    }

    public function test_logout_and_logout_everywhere(): void
    {
        $user = $this->customer();
        [$first, $firstHeaders] = $this->signedInPhone($user);
        [$second, $secondHeaders] = $this->signedInPhone($user);
        [$third, $thirdHeaders] = $this->signedInPhone($user);

        $this->postJson('/api/v1/mobile/logout', [], $firstHeaders)->assertOk();
        $this->assertNotNull($first->fresh()->revoked_at);
        $this->getJson('/api/v1/mobile/home', $firstHeaders)->assertUnauthorized();
        $this->getJson('/api/v1/mobile/home', $secondHeaders)->assertOk();

        $this->postJson('/api/v1/mobile/logout-everywhere', [], $secondHeaders)->assertOk();
        $this->assertNotNull($second->fresh()->revoked_at);
        $this->assertNotNull($third->fresh()->revoked_at);
        $this->getJson('/api/v1/mobile/home', $thirdHeaders)->assertUnauthorized();
    }

    public function test_deleting_the_account_needs_the_password(): void
    {
        $user = $this->customer(['password' => Hash::make('correct-horse-battery')]);
        [, $headers] = $this->signedInPhone($user, ['authenticated_at' => now()->subDay()]);

        $this->deleteJson('/api/v1/mobile/account', ['password' => 'wrong'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/v1/mobile/account', ['confirm' => 'DELETE'], $headers)->assertUnprocessable();
        $this->assertNotSoftDeleted($user);

        $this->deleteJson('/api/v1/mobile/account', ['password' => 'correct-horse-battery'], $headers)->assertOk();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseCount('api_tokens', 0);
        $this->getJson('/api/v1/mobile/home', $headers)->assertUnauthorized();
    }

    public function test_an_account_without_a_password_can_delete_right_after_signing_in(): void
    {
        $user = $this->customer(['google_id' => 'google-123']);
        [, $headers] = $this->signedInPhone($user, ['authenticated_at' => now()->subMinutes(2)]);

        $this->deleteJson('/api/v1/mobile/account', ['confirm' => 'DELETE'], $headers)->assertOk();

        $this->assertSoftDeleted($user);
    }

    /**
     * A throwaway endpoint with the same billing and logging middleware
     * the paid v1 endpoints use, costing 5 cents a call.
     */
    protected function meteredRoute(): string
    {
        Route::middleware(['log-api-request', 'api-token-auth', 'meter-api-usage'])
            ->get('/api/test-metered', fn () => response()->json(['ok' => true]))
            ->name('test.metered');

        config(['api_billing.costs' => ['test.metered' => 5]]);

        return '/api/test-metered';
    }

    public function test_app_calls_are_never_billed_or_logged_as_api_usage(): void
    {
        $url = $this->meteredRoute();
        $user = $this->customer(['api_wallet_balance_cents' => 100]);
        [, $headers] = $this->signedInPhone($user);

        $this->getJson($url, $headers)->assertOk();

        $this->assertSame(100, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame(0, ApiRequestLog::query()->count());
    }

    public function test_developer_tokens_are_still_billed_and_logged_as_before(): void
    {
        $url = $this->meteredRoute();
        $user = $this->customer(['api_wallet_balance_cents' => 100]);
        $plain = ApiToken::generate($user, 'Script')['plainText'];

        $this->getJson($url, ['Authorization' => "Bearer {$plain}"])->assertOk();

        $this->assertSame(95, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame(1, ApiRequestLog::query()->count());
    }

    public function test_partner_only_accounts_are_refused_even_with_a_token(): void
    {
        $user = $this->customer(['is_affiliate_only' => true]);
        [, $headers] = $this->signedInPhone($user);

        $this->getJson('/api/v1/mobile/home', $headers)->assertForbidden();
    }
}
