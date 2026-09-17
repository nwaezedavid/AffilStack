<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\TikTokSetting;
use App\Models\User;
use App\Services\Social\TikTokPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Task #2: connecting + publishing a rendered ugc_video to TikTok via the
 * Content Posting API (PULL_FROM_URL) — see
 * App\Services\Social\TikTokPublishingService. Every new TikTok app starts
 * capped/private until TikTok's own audit approves it, so publish() always
 * refuses first, with no privacy-downgraded fallback publish attempted.
 */
class TikTokPublishingServiceTest extends TestCase
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

    protected function enableTikTok(bool $approved = false): void
    {
        TikTokSetting::current()->update([
            'is_enabled' => true,
            'approval_status' => $approved ? 'approved' : 'not_submitted',
            'credentials' => ['client_key' => 'key123', 'client_secret' => 'secret456'],
        ]);
    }

    protected function connection(): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'tiktok',
            'credentials' => ['access_token' => 'still-good', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()],
            'account_name' => 'acme_official',
            'account_id' => 'open1',
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
        $this->assertFalse(app(TikTokPublishingService::class)->isAvailable());

        $this->enableTikTok();

        $this->assertTrue(app(TikTokPublishingService::class)->isAvailable());
    }

    public function test_exchange_code_stores_tokens_and_resolves_the_profile(): void
    {
        $this->enableTikTok();
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open1', 'display_name' => 'acme_official']]]),
        ]);

        $result = app(TikTokPublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');

        $this->assertSame('at1', $result['credentials']['access_token']);
        $this->assertSame('acme_official', $result['account_name']);
        $this->assertSame('open1', $result['account_id']);
    }

    public function test_exchange_code_fails_when_no_refresh_token_is_granted(): void
    {
        $this->enableTikTok();
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'at1', 'expires_in' => 3600]),
        ]);

        $this->expectException(RuntimeException::class);

        app(TikTokPublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');
    }

    public function test_publish_refuses_until_tiktok_has_approved_the_app(): void
    {
        $this->enableTikTok(approved: false);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake();

        $this->expectException(RuntimeException::class);

        app(TikTokPublishingService::class)->publish($connection, $video, 'Title', 'Caption');

        Http::assertNothingSent();
    }

    public function test_publish_initiates_a_pull_from_url_post_once_approved(): void
    {
        $this->enableTikTok(approved: true);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'pub1']]),
        ]);

        $result = app(TikTokPublishingService::class)->publish($connection, $video, 'Title', 'Check it out');

        $this->assertSame('pub1', $result['external_id']);
        $this->assertNull($result['url']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'post/publish/video/init')
            && $request['source_info']['video_url'] === 'https://cdn.example/video.mp4'
            && $request['post_info']['privacy_level'] === 'PUBLIC_TO_EVERYONE');
    }

    public function test_publish_refreshes_an_expired_access_token_and_rotates_the_refresh_token(): void
    {
        $this->enableTikTok(approved: true);
        $connection = SocialConnection::create([
            'user_id' => $this->user->id, 'provider' => 'tiktok',
            'credentials' => ['access_token' => 'expired', 'refresh_token' => 'rt1', 'expires_at' => now()->subMinute()->toIso8601String()],
            'account_name' => 'acme_official', 'account_id' => 'open1', 'connected_at' => now(),
        ]);
        $video = $this->video();

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'rt2', 'expires_in' => 3600]),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'pub1']]),
        ]);

        app(TikTokPublishingService::class)->publish($connection, $video, 'Title', 'Check it out');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'post/publish')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
        $this->assertSame('fresh-token', $connection->fresh()->credential('access_token'));
        $this->assertSame('rt2', $connection->fresh()->credential('refresh_token'));
    }
}
