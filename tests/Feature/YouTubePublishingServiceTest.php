<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\GoogleOauthSetting;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Social\YouTubePublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Task #2: connecting + publishing a rendered ugc_video to YouTube — see
 * App\Services\Social\YouTubePublishingService. Gated on
 * GoogleOauthSetting::isYoutubeApprovedForPublishing() so publish() always
 * refuses until YouTube's own Audit + Quota Extension is granted, matching
 * the "fall back to manual download" requirement.
 */
class YouTubePublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->offer = Offer::create([
            'user_id' => $this->user->id, 'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget', 'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);
    }

    protected function enableYoutube(bool $approved = false): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'youtube_publishing_enabled' => true,
            'youtube_approval_status' => $approved ? 'approved' : 'not_submitted',
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    protected function connection(): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'youtube',
            'credentials' => ['access_token' => 'still-good', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()],
            'account_name' => 'Acme Channel',
            'account_id' => 'chan1',
            'connected_at' => now(),
        ]);
    }

    protected function video(): Generation
    {
        return Generation::create([
            'user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'ugc_video',
            'status' => 'completed', 'output' => 'https://cdn.example/video.mp4',
            'output_meta' => ['heygen_video_id' => 'vid1', 'video_url' => 'https://cdn.example/video.mp4'],
        ]);
    }

    public function test_is_available_requires_the_toggle_and_credentials(): void
    {
        $this->assertFalse(app(YouTubePublishingService::class)->isAvailable());

        $this->enableYoutube();

        $this->assertTrue(app(YouTubePublishingService::class)->isAvailable());
    }

    public function test_is_approved_for_publishing_reflects_the_admin_approval_status(): void
    {
        $this->enableYoutube(approved: false);
        $this->assertFalse(app(YouTubePublishingService::class)->isApprovedForPublishing());

        $this->enableYoutube(approved: true);
        $this->assertTrue(app(YouTubePublishingService::class)->isApprovedForPublishing());
    }

    public function test_exchange_code_stores_offline_tokens_and_resolves_the_channel(): void
    {
        $this->enableYoutube();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'chan1', 'snippet' => ['title' => 'Acme Channel']]]]),
        ]);

        $result = app(YouTubePublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');

        $this->assertSame('at1', $result['credentials']['access_token']);
        $this->assertSame('rt1', $result['credentials']['refresh_token']);
        $this->assertSame('Acme Channel', $result['account_name']);
        $this->assertSame('chan1', $result['account_id']);
    }

    public function test_exchange_code_fails_when_no_refresh_token_is_granted(): void
    {
        $this->enableYoutube();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'expires_in' => 3600]),
        ]);

        $this->expectException(RuntimeException::class);

        app(YouTubePublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');
    }

    public function test_exchange_code_fails_when_the_account_has_no_channel(): void
    {
        $this->enableYoutube();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []]),
        ]);

        $this->expectException(RuntimeException::class);

        app(YouTubePublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');
    }

    public function test_publish_refuses_until_youtube_has_approved_the_app(): void
    {
        $this->enableYoutube(approved: false);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake();

        $this->expectException(RuntimeException::class);

        app(YouTubePublishingService::class)->publish($connection, $video, 'Title', 'Caption');

        Http::assertNothingSent();
    }

    public function test_publish_uploads_the_video_once_approved(): void
    {
        $this->enableYoutube(approved: true);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake([
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/*' => Http::response(['id' => 'yt-video-1']),
        ]);

        $result = app(YouTubePublishingService::class)->publish($connection, $video, 'My Video', 'Check it out');

        $this->assertSame('yt-video-1', $result['external_id']);
        $this->assertStringContainsString('yt-video-1', $result['url']);
    }

    public function test_publish_refreshes_an_expired_access_token(): void
    {
        $this->enableYoutube(approved: true);
        $connection = SocialConnection::create([
            'user_id' => $this->user->id, 'provider' => 'youtube',
            'credentials' => ['access_token' => 'expired', 'refresh_token' => 'rt1', 'expires_at' => now()->subMinute()->toIso8601String()],
            'account_name' => 'Acme Channel', 'account_id' => 'chan1', 'connected_at' => now(),
        ]);
        $video = $this->video();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/*' => Http::response(['id' => 'yt-video-1']),
        ]);

        app(YouTubePublishingService::class)->publish($connection, $video, 'My Video', 'Check it out');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'upload/youtube')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
        $this->assertSame('fresh-token', $connection->fresh()->credential('access_token'));
    }
}
