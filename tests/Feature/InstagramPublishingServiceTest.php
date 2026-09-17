<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\InstagramSetting;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Social\InstagramPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Task #2: connecting + publishing a rendered ugc_video to Instagram as a
 * Reel via the Meta Graph API's container flow — see
 * App\Services\Social\InstagramPublishingService. Gated on
 * InstagramSetting::isApprovedForPublishing() (Meta App Review) so
 * publish() always refuses first.
 */
class InstagramPublishingServiceTest extends TestCase
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

    protected function enableInstagram(bool $approved = false): void
    {
        InstagramSetting::current()->update([
            'is_enabled' => true,
            'approval_status' => $approved ? 'approved' : 'not_submitted',
            'credentials' => ['app_id' => 'app123', 'app_secret' => 'secret456'],
        ]);
    }

    protected function connection(): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'instagram',
            'credentials' => ['page_access_token' => 'page-token', 'ig_user_id' => 'ig1'],
            'account_name' => '@acme_official',
            'account_id' => 'ig1',
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
        $this->assertFalse(app(InstagramPublishingService::class)->isAvailable());

        $this->enableInstagram();

        $this->assertTrue(app(InstagramPublishingService::class)->isAvailable());
    }

    public function test_exchange_code_finds_the_connected_page_and_its_instagram_account(): void
    {
        $this->enableInstagram();
        Http::fake([
            'graph.facebook.com/v19.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/v19.0/me/accounts*' => Http::response(['data' => [
                ['name' => 'Acme Page', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => 'ig1']],
            ]]),
            'graph.facebook.com/v19.0/ig1*' => Http::response(['username' => 'acme_official']),
        ]);

        $result = app(InstagramPublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');

        $this->assertSame('page-token', $result['credentials']['page_access_token']);
        $this->assertSame('ig1', $result['credentials']['ig_user_id']);
        $this->assertSame('@acme_official', $result['account_name']);
        $this->assertSame('ig1', $result['account_id']);
    }

    public function test_exchange_code_fails_when_no_page_has_a_linked_instagram_account(): void
    {
        $this->enableInstagram();
        Http::fake([
            'graph.facebook.com/v19.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/v19.0/me/accounts*' => Http::response(['data' => [
                ['name' => 'Acme Page', 'access_token' => 'page-token'],
            ]]),
        ]);

        $this->expectException(RuntimeException::class);

        app(InstagramPublishingService::class)->exchangeCode('auth-code', 'https://app.example/callback');
    }

    public function test_publish_refuses_until_meta_has_approved_the_app(): void
    {
        $this->enableInstagram(approved: false);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake();

        $this->expectException(RuntimeException::class);

        app(InstagramPublishingService::class)->publish($connection, $video, 'Title', 'Caption');

        Http::assertNothingSent();
    }

    public function test_publish_creates_a_reel_container_waits_then_publishes_it(): void
    {
        $this->enableInstagram(approved: true);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake([
            'graph.facebook.com/v19.0/ig1/media' => Http::response(['id' => 'container1']),
            'graph.facebook.com/v19.0/container1?*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/v19.0/ig1/media_publish' => Http::response(['id' => 'media1']),
            'graph.facebook.com/v19.0/media1*' => Http::response(['permalink' => 'https://instagram.com/reel/abc']),
        ]);

        $result = app(InstagramPublishingService::class)->publish($connection, $video, 'Title', 'Check it out');

        $this->assertSame('media1', $result['external_id']);
        $this->assertSame('https://instagram.com/reel/abc', $result['url']);
    }

    public function test_publish_fails_if_the_container_never_finishes_processing(): void
    {
        $this->enableInstagram(approved: true);
        $connection = $this->connection();
        $video = $this->video();

        Http::fake([
            'graph.facebook.com/v19.0/ig1/media' => Http::response(['id' => 'container1']),
            'graph.facebook.com/v19.0/container1?*' => Http::response(['status_code' => 'ERROR']),
        ]);

        $this->expectException(RuntimeException::class);

        app(InstagramPublishingService::class)->publish($connection, $video, 'Title', 'Check it out');
    }
}
