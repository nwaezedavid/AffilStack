<?php

namespace Tests\Feature;

use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use App\Models\User;
use App\Services\Crm\GmailOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Task #1: the Gmail-sending OAuth flow's low-level mechanics — separate
 * from the sign-in flow's GoogleOAuthService (different scope, offline
 * access, and it actually sends mail via the Gmail API afterward).
 */
class GmailOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function enableGmailSending(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'gmail_sending_enabled' => true,
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    public function test_is_available_requires_the_toggle_and_credentials(): void
    {
        $this->assertFalse(app(GmailOAuthService::class)->isAvailable());

        $this->enableGmailSending();

        $this->assertTrue(app(GmailOAuthService::class)->isAvailable());
    }

    public function test_authorization_url_requests_offline_access_and_the_gmail_send_scope(): void
    {
        $this->enableGmailSending();

        $url = app(GmailOAuthService::class)->authorizationUrl('https://app.example/callback', 'state123');

        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/gmail.send'), $url);
    }

    public function test_exchange_code_stores_tokens_and_resolves_the_connected_email(): void
    {
        $this->enableGmailSending();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'sender@gmail.com']),
        ]);

        $result = app(GmailOAuthService::class)->exchangeCode('auth-code', 'https://app.example/callback');

        $this->assertSame('at1', $result['access_token']);
        $this->assertSame('rt1', $result['refresh_token']);
        $this->assertSame('sender@gmail.com', $result['email']);
    }

    public function test_exchange_code_fails_loudly_when_google_does_not_grant_offline_access(): void
    {
        $this->enableGmailSending();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'expires_in' => 3600]),
        ]);

        $this->expectException(RuntimeException::class);

        app(GmailOAuthService::class)->exchangeCode('auth-code', 'https://app.example/callback');
    }

    public function test_send_reuses_an_unexpired_access_token_without_refreshing(): void
    {
        $this->enableGmailSending();
        $user = User::factory()->create();
        $connection = EmailConnection::create([
            'user_id' => $user->id,
            'provider' => 'gmail',
            'credentials' => ['access_token' => 'still-good', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()],
            'connected_email' => 'sender@gmail.com',
            'verification_status' => 'success',
        ]);

        Http::fake([
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg1']),
        ]);

        app(GmailOAuthService::class)->send($connection, 'to@example.com', 'Subject', '<p>Body</p>');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail.googleapis.com')
            && $request->hasHeader('Authorization', 'Bearer still-good'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token'));
    }

    public function test_send_refreshes_an_expired_access_token_and_persists_the_new_one(): void
    {
        $this->enableGmailSending();
        $user = User::factory()->create();
        $connection = EmailConnection::create([
            'user_id' => $user->id,
            'provider' => 'gmail',
            'credentials' => ['access_token' => 'expired', 'refresh_token' => 'rt1', 'expires_at' => now()->subMinute()->toIso8601String()],
            'connected_email' => 'sender@gmail.com',
            'verification_status' => 'success',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg1']),
        ]);

        app(GmailOAuthService::class)->send($connection, 'to@example.com', 'Subject', '<p>Body</p>');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail.googleapis.com')
            && $request->hasHeader('Authorization', 'Bearer new-token'));

        $this->assertSame('new-token', $connection->fresh()->credential('access_token'));
        $this->assertSame('rt1', $connection->fresh()->credential('refresh_token'));
    }

    public function test_send_throws_when_the_gmail_api_rejects_the_message(): void
    {
        $this->enableGmailSending();
        $user = User::factory()->create();
        $connection = EmailConnection::create([
            'user_id' => $user->id,
            'provider' => 'gmail',
            'credentials' => ['access_token' => 'still-good', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()],
            'connected_email' => 'sender@gmail.com',
            'verification_status' => 'success',
        ]);

        Http::fake([
            'gmail.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 403),
        ]);

        $this->expectException(RuntimeException::class);

        app(GmailOAuthService::class)->send($connection, 'to@example.com', 'Subject', '<p>Body</p>');
    }
}
