<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleOAuthSettings;
use App\Models\Generation;
use App\Models\GoogleOauthSetting;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Social\YouTubePublishingService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * While Google reviews the Gmail and YouTube scopes, only listed test
 * accounts (and admins) can start those connections, and they can publish
 * to YouTube for real so the reviewer sees the upload arrive.
 */
class GoogleReviewTestersTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->customer = User::factory()->create(['email' => 'customer@example.com']);
        $this->customer->assignRole('user');
        $this->tester = User::factory()->create(['email' => 'Reviewer@Example.com']);
        $this->tester->assignRole('user');

        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'gmail_sending_enabled' => true,
            'youtube_publishing_enabled' => true,
            'youtube_approval_status' => 'pending',
            'limit_to_review_testers' => true,
            'review_testers' => "reviewer@example.com\nother@example.com",
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    public function test_only_listed_testers_see_connect_gmail_while_the_limit_is_on(): void
    {
        $this->actingAs($this->customer)->get(route('email-connections.index'))->assertSee("Gmail connections aren't available yet", false);
        $this->actingAs($this->customer)->get(route('email-connections.gmail.redirect'))->assertSessionHas('error');

        $this->actingAs($this->tester)->get(route('email-connections.index'))->assertDontSee("Gmail connections aren't available yet", false);
        $redirect = $this->actingAs($this->tester)->get(route('email-connections.gmail.redirect'));
        $this->assertStringContainsString('accounts.google.com', (string) $redirect->headers->get('Location'));
    }

    public function test_only_listed_testers_can_start_a_youtube_connection_while_the_limit_is_on(): void
    {
        $this->actingAs($this->customer)
            ->get(route('social-connections.redirect', 'youtube'))
            ->assertSessionHas('error');

        $redirect = $this->actingAs($this->tester)->get(route('social-connections.redirect', 'youtube'));
        $this->assertStringContainsString('accounts.google.com', (string) $redirect->headers->get('Location'));
    }

    public function test_everyone_can_connect_once_the_limit_is_off(): void
    {
        GoogleOauthSetting::current()->update(['limit_to_review_testers' => false]);

        $this->actingAs($this->customer)->get(route('email-connections.index'))->assertDontSee("Gmail connections aren't available yet", false);
    }

    public function test_admins_count_as_testers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(GoogleOauthSetting::current()->isReviewTester($admin));
        $this->assertFalse(GoogleOauthSetting::current()->isReviewTester($this->customer));
    }

    public function test_a_tester_can_publish_to_youtube_before_the_audit_is_approved(): void
    {
        $offer = Offer::create([
            'user_id' => $this->tester->id, 'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget', 'affiliate_network' => 'ShareASale', 'status' => 'ready',
        ]);
        $video = Generation::create([
            'user_id' => $this->tester->id, 'offer_id' => $offer->id, 'module' => 'ugc_video',
            'status' => 'completed', 'output' => 'https://cdn.example/video.mp4',
        ]);
        $connection = SocialConnection::create([
            'user_id' => $this->tester->id, 'provider' => 'youtube',
            'credentials' => ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => now()->addHour()->toIso8601String()],
            'account_name' => 'Reviewer Channel', 'account_id' => 'chan1', 'connected_at' => now(),
        ]);

        Http::fake([
            'https://cdn.example/video.mp4' => Http::response('binary-bytes'),
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/*' => Http::response(['id' => 'yt-video-1']),
        ]);

        $result = app(YouTubePublishingService::class)->publish($connection, $video, 'Title', 'Caption');

        $this->assertSame('yt-video-1', $result['external_id']);
    }

    public function test_the_admin_saves_the_tester_list(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);

        Livewire::actingAs($admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm(['limit_to_review_testers' => false, 'review_testers' => 'new@example.com'])
            ->call('save');

        $settings = GoogleOauthSetting::current();
        $this->assertFalse($settings->limit_to_review_testers);
        $this->assertSame(['new@example.com'], $settings->reviewTesterEmails());
    }
}
