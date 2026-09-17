<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\GoogleOauthSetting;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task #2: the "Publish to YouTube/TikTok/Instagram" button on a rendered
 * ugc_video generation — see App\Http\Controllers\Dashboard\SocialPublishController.
 * Every failure mode (not connected, not yet approved, a rejected API call)
 * must fall back gracefully to the always-available manual download rather
 * than ever erroring the page.
 */
class SocialPublishControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        $this->offer = Offer::create([
            'user_id' => $this->user->id, 'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget', 'affiliate_network' => 'ShareASale',
            'status' => 'ready',
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

    protected function enableAndApproveYoutube(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'youtube_publishing_enabled' => true,
            'youtube_approval_status' => 'approved',
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    protected function connectYoutube(): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id, 'provider' => 'youtube',
            'credentials' => ['access_token' => 'still-good', 'refresh_token' => 'rt1', 'expires_at' => now()->addHour()->toIso8601String()],
            'account_name' => 'Acme Channel', 'account_id' => 'chan1', 'connected_at' => now(),
        ]);
    }

    public function test_publishing_requires_the_platform_to_be_connected_first(): void
    {
        $video = $this->video();

        $response = $this->actingAs($this->user)->post(route('generations.publish', [$video, 'youtube']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($video->fresh()->output_meta['published']['youtube'] ?? null);
    }

    public function test_publishing_falls_back_gracefully_when_the_platform_has_not_approved_the_app_yet(): void
    {
        // Connected but never approved by Google — the default state.
        GoogleOauthSetting::current()->update(['is_enabled' => true, 'youtube_publishing_enabled' => true, 'credentials' => ['client_id' => 'x', 'client_secret' => 'y']]);
        $this->connectYoutube();
        $video = $this->video();

        $response = $this->actingAs($this->user)->post(route('generations.publish', [$video, 'youtube']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('download the video', session('error'));
    }

    public function test_publishing_succeeds_and_records_the_result_on_the_generation(): void
    {
        $this->enableAndApproveYoutube();
        $this->connectYoutube();
        $video = $this->video();

        Http::fake([
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/*' => Http::response(['id' => 'yt-video-1']),
        ]);

        $response = $this->actingAs($this->user)->post(route('generations.publish', [$video, 'youtube']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $published = $video->fresh()->output_meta['published']['youtube'] ?? null;
        $this->assertNotNull($published);
        $this->assertSame('yt-video-1', $published['external_id']);
        $this->assertStringContainsString('yt-video-1', $published['url']);
    }

    public function test_publishing_a_real_api_failure_flashes_the_error_without_recording_a_publish(): void
    {
        $this->enableAndApproveYoutube();
        $this->connectYoutube();
        $video = $this->video();

        Http::fake([
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('rejected', 403),
        ]);

        $response = $this->actingAs($this->user)->post(route('generations.publish', [$video, 'youtube']));

        $response->assertSessionHas('error');
        $this->assertNull($video->fresh()->output_meta['published']['youtube'] ?? null);
    }

    public function test_publishing_a_non_ugc_video_generation_404s(): void
    {
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'blog_article', 'status' => 'completed']);

        $this->actingAs($this->user)
            ->post(route('generations.publish', [$generation, 'youtube']))
            ->assertNotFound();
    }

    public function test_publishing_to_an_unknown_provider_404s(): void
    {
        $video = $this->video();

        $this->actingAs($this->user)
            ->post(route('generations.publish', [$video, 'facebook']))
            ->assertNotFound();
    }

    public function test_a_user_cannot_publish_another_users_video(): void
    {
        $this->enableAndApproveYoutube();
        $this->connectYoutube();
        $video = $this->video();
        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($other)
            ->post(route('generations.publish', [$video, 'youtube']))
            ->assertForbidden();
    }

    public function test_the_caption_and_title_are_pulled_from_the_linked_ugc_content_generation_and_cloaked(): void
    {
        $this->enableAndApproveYoutube();
        $this->connectYoutube();

        $contentGeneration = Generation::create([
            'user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'ugc_content', 'status' => 'completed',
            'output_meta' => ['platforms' => [
                ['platform' => 'YouTube Shorts', 'title' => 'My Great Video', 'caption' => 'Grab it here {{AFFILIATE_LINK}}'],
            ]],
        ]);
        $video = Generation::create([
            'user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'ugc_video', 'status' => 'completed',
            'output' => 'https://cdn.example/video.mp4', 'input' => ['content_generation_id' => $contentGeneration->id],
            'output_meta' => ['heygen_video_id' => 'vid1', 'video_url' => 'https://cdn.example/video.mp4'],
        ]);

        Http::fake([
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/*' => Http::response(['id' => 'yt-video-1']),
        ]);

        $this->actingAs($this->user)->post(route('generations.publish', [$video, 'youtube']));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'upload/youtube')) {
                return true;
            }

            return $request['snippet']['title'] === 'My Great Video'
                && ! str_contains($request['snippet']['description'], '{{AFFILIATE_LINK}}')
                && str_contains($request['snippet']['description'], 'Grab it here');
        });
    }
}
