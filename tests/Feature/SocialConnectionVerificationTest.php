<?php

namespace Tests\Feature;

use App\Models\GoogleOauthSetting;
use App\Models\InstagramSetting;
use App\Models\LinkedInOauthSetting;
use App\Models\SocialConnection;
use App\Models\TikTokSetting;
use App\Models\User;
use App\Notifications\SocialConnectionBroken;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Live connection verification on Connected Accounts — see
 * SocialConnectionVerifier and each provider's verify(): run right after
 * connecting, on "Test connection", and daily by social-connections:verify,
 * notifying the owner once per break.
 */
class SocialConnectionVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    protected function enableYoutube(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'youtube_publishing_enabled' => true,
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    protected function enableLinkedIn(): void
    {
        LinkedInOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'client123', 'client_secret' => 'secret456'],
        ]);
    }

    protected function enableTikTok(): void
    {
        TikTokSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_key' => 'key123', 'client_secret' => 'secret456'],
        ]);
    }

    protected function enableInstagram(): void
    {
        InstagramSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['app_id' => 'app123', 'app_secret' => 'secret456'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $attributes
     */
    protected function connect(string $provider, array $credentials, array $attributes = []): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => $provider,
            'credentials' => $credentials,
            'account_name' => 'Old name',
            'account_id' => 'acc1',
            'connected_at' => now()->subWeek(),
            ...$attributes,
        ]);
    }

    protected function youtubeConnection(array $attributes = []): SocialConnection
    {
        return $this->connect('youtube', ['access_token' => 'expired-at', 'refresh_token' => 'rt1', 'expires_at' => now()->subMinute()->toIso8601String()], $attributes);
    }

    public function test_connecting_verifies_the_new_connection_straight_away(): void
    {
        $this->enableLinkedIn();
        Http::fake([
            'linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'tok1', 'expires_in' => 5184000, 'scope' => 'openid,profile,email']),
            'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'abc123', 'name' => 'Jordan Smith', 'picture' => 'https://media.licdn.com/jordan.jpg']),
        ]);

        $this->actingAs($this->user)->get(route('social-connections.redirect', 'linkedin'));
        $state = session('social_connect_state');

        $this->actingAs($this->user)
            ->get(route('social-connections.callback', ['provider' => 'linkedin', 'state' => $state, 'code' => 'auth-code']))
            ->assertRedirect(route('social-connections.index'))
            ->assertSessionHas('success', 'LinkedIn connected and verified as Jordan Smith.');

        $connection = SocialConnection::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('verified', $connection->verification_status);
        $this->assertNotNull($connection->last_verified_at);
        $this->assertSame('https://media.licdn.com/jordan.jpg', $connection->avatar_url);
        $this->assertSame(['openid', 'profile', 'email'], $connection->granted_scopes);
        Http::assertSentCount(3);
    }

    public function test_test_connection_refreshes_the_token_and_marks_the_connection_verified(): void
    {
        $this->enableYoutube();
        $connection = $this->youtubeConnection();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-at', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [[
                'id' => 'chan1',
                'snippet' => ['title' => 'Acme Channel', 'thumbnails' => ['default' => ['url' => 'https://yt3.ggpht.com/acme.jpg']]],
            ]]]),
        ]);

        $this->actingAs($this->user)
            ->post(route('social-connections.verify', 'youtube'))
            ->assertRedirect(route('social-connections.index'))
            ->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame('verified', $connection->verification_status);
        $this->assertSame('Acme Channel', $connection->account_name);
        $this->assertSame('https://yt3.ggpht.com/acme.jpg', $connection->avatar_url);
        $this->assertSame('new-at', $connection->credential('access_token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'channels') && $request->hasHeader('Authorization', 'Bearer new-at'));
    }

    public function test_a_revoked_refresh_token_marks_the_connection_expired(): void
    {
        $this->enableYoutube();
        $connection = $this->youtubeConnection();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->actingAs($this->user)
            ->post(route('social-connections.verify', 'youtube'))
            ->assertSessionHas('error');

        $connection->refresh();
        $this->assertSame('expired', $connection->verification_status);
        $this->assertStringContainsString('reconnect', strtolower($connection->verification_message));
    }

    public function test_a_provider_outage_marks_the_check_failed_without_notifying_on_a_manual_test(): void
    {
        Notification::fake();
        $this->enableLinkedIn();
        $connection = $this->connect('linkedin', ['access_token' => 'tok1'], ['verification_status' => 'failed']);
        Http::fake(['api.linkedin.com/v2/userinfo' => Http::response(['message' => 'Service unavailable'], 503)]);

        $this->actingAs($this->user)->post(route('social-connections.verify', 'linkedin'))->assertSessionHas('error');

        $this->assertSame('failed', $connection->fresh()->verification_status);
        $this->assertStringContainsString('Service unavailable', $connection->fresh()->verification_message);
        Notification::assertNothingSent();
    }

    public function test_a_tiktok_invalid_token_marks_the_connection_expired(): void
    {
        $this->enableTikTok();
        $connection = $this->connect('tiktok', ['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()]);
        Http::fake(['open.tiktokapis.com/v2/user/info/*' => Http::response(['error' => ['code' => 'access_token_invalid', 'message' => 'The access token is invalid']], 401)]);

        $this->actingAs($this->user)->post(route('social-connections.verify', 'tiktok'));

        $this->assertSame('expired', $connection->fresh()->verification_status);
    }

    public function test_an_instagram_oauth_exception_marks_the_connection_expired_and_a_good_one_verifies(): void
    {
        $this->enableInstagram();
        $connection = $this->connect('instagram', ['page_access_token' => 'page-token', 'ig_user_id' => 'ig1']);

        Http::fake(['graph.facebook.com/v19.0/ig1*' => Http::sequence()
            ->push(['error' => ['message' => 'Error validating access token', 'type' => 'OAuthException', 'code' => 190]], 400)
            ->push(['username' => 'acme_official', 'profile_picture_url' => 'https://scontent.cdninstagram.com/acme.jpg']),
        ]);

        $this->actingAs($this->user)->post(route('social-connections.verify', 'instagram'));
        $this->assertSame('expired', $connection->fresh()->verification_status);

        $this->actingAs($this->user)->post(route('social-connections.verify', 'instagram'));
        $this->assertSame('verified', $connection->fresh()->verification_status);
        $this->assertSame('@acme_official', $connection->fresh()->account_name);
    }

    public function test_the_daily_recheck_notifies_once_when_a_connection_expires_and_resets_after_it_recovers(): void
    {
        Notification::fake();
        $this->enableYoutube();
        $connection = $this->youtubeConnection(['verification_status' => 'verified', 'last_verified_at' => now()->subDay()]);

        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->artisan('social-connections:verify')->assertSuccessful();
        $this->artisan('social-connections:verify', ['--stale-hours' => 0])->assertSuccessful();

        $this->assertSame('expired', $connection->fresh()->verification_status);
        $this->assertNotNull($connection->fresh()->broken_notified_at);
        Notification::assertSentToTimes($this->user, SocialConnectionBroken::class, 1);
        Notification::assertSentTo($this->user, SocialConnectionBroken::class, fn (SocialConnectionBroken $n) => $n->toArray($this->user)['reason'] === 'Your YouTube connection needs reconnecting');

        // The user reconnects (fresh token) — the next check clears the flag.
        $connection->update(['credentials' => ['access_token' => 'fresh', 'refresh_token' => 'rt2', 'expires_at' => now()->addHour()->toIso8601String()]]);
        Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'chan1', 'snippet' => ['title' => 'Acme Channel']]]])]);

        $this->artisan('social-connections:verify', ['--stale-hours' => 0])->assertSuccessful();

        $this->assertSame('verified', $connection->fresh()->verification_status);
        $this->assertNull($connection->fresh()->broken_notified_at);
    }

    public function test_a_single_failed_check_does_not_notify_but_a_second_in_a_row_does(): void
    {
        Notification::fake();
        $this->enableLinkedIn();
        $connection = $this->connect('linkedin', ['access_token' => 'tok1'], ['verification_status' => 'verified']);
        Http::fake(['api.linkedin.com/v2/userinfo' => Http::response([], 500)]);

        $this->artisan('social-connections:verify')->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('social-connections:verify', ['--stale-hours' => 0])->assertSuccessful();
        Notification::assertSentToTimes($this->user, SocialConnectionBroken::class, 1);
        $this->assertSame('failed', $connection->fresh()->verification_status);
    }

    public function test_the_daily_recheck_skips_recently_checked_and_switched_off_providers(): void
    {
        $this->enableLinkedIn();
        $recent = $this->connect('linkedin', ['access_token' => 'tok1'], ['verification_status' => 'verified', 'last_verified_at' => now()->subHour()]);
        $switchedOff = $this->connect('tiktok', ['access_token' => 'at1']);
        Http::fake();

        $this->artisan('social-connections:verify')->assertSuccessful()->expectsOutputToContain('Checked 0');

        Http::assertNothingSent();
        $this->assertNull($switchedOff->fresh()->verification_status);
        $this->assertSame('verified', $recent->fresh()->verification_status);
    }

    public function test_the_daily_recheck_is_scheduled(): void
    {
        $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('social-connections:verify');
    }

    public function test_the_page_shows_live_status_reconnect_and_not_available_states(): void
    {
        $this->enableYoutube();
        $this->enableLinkedIn();
        $this->youtubeConnection([
            'account_name' => 'Acme Channel',
            'verification_status' => 'expired',
            'verification_message' => 'Google rejected the saved YouTube access.',
            'last_verified_at' => now()->subHours(2),
        ]);
        $this->connect('linkedin', ['access_token' => 'tok1'], ['account_name' => 'Jordan Smith', 'verification_status' => 'verified', 'last_verified_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('Acme Channel')
            ->assertSee('Reconnect needed')
            ->assertSee('Google rejected the saved YouTube access.')
            ->assertSee('Reconnect YouTube')
            ->assertSee('Verified')
            ->assertSee('Test connection')
            ->assertSee('Last checked 2 hours ago')
            ->assertSee('Not available yet')
            ->assertSee("TikTok connections aren't available yet", false)
            ->assertSee('needs your attention');
    }

    public function test_the_page_shows_the_not_connected_state_with_what_connecting_unlocks(): void
    {
        $this->enableTikTok();

        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('Not connected')
            ->assertSee('What connecting unlocks')
            ->assertSee('Continue with TikTok')
            ->assertSee(route('social-connections.redirect', 'tiktok'))
            ->assertDontSee('Test connection');
    }

    public function test_testing_a_provider_that_is_not_connected_fails_gracefully(): void
    {
        $this->actingAs($this->user)
            ->post(route('social-connections.verify', 'youtube'))
            ->assertRedirect(route('social-connections.index'))
            ->assertSessionHas('error', "YouTube isn't connected yet.");

        $this->actingAs($this->user)->post(route('social-connections.verify', 'myspace'))->assertNotFound();
    }

    public function test_the_publish_status_component_reflects_each_state(): void
    {
        $this->enableYoutube();
        $this->actingAs($this->user);

        $html = Blade::render('<x-connections.publish-status provider="youtube" />');
        $this->assertStringContainsString('YouTube not connected', $html);
        $this->assertStringContainsString('Connect', $html);

        $this->youtubeConnection(['verification_status' => 'expired']);
        $html = Blade::render('<x-connections.publish-status provider="youtube" />');
        $this->assertStringContainsString('YouTube: reconnect', $html);
        $this->assertStringContainsString('Reconnect', $html);

        SocialConnection::query()->update(['verification_status' => 'verified']);
        $html = Blade::render('<x-connections.publish-status provider="youtube" />');
        $this->assertStringContainsString('YouTube verified', $html);
        $this->assertStringNotContainsString(route('social-connections.index'), $html);

        $html = Blade::render('<x-connections.publish-status provider="tiktok" />');
        $this->assertStringContainsString('TikTok unavailable', $html);
    }
}
