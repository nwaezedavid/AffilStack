<?php

namespace Tests\Feature;

use App\Models\GoogleOauthSetting;
use App\Models\InstagramSetting;
use App\Models\LinkedInOauthSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\SocialConnection;
use App\Models\Subscription;
use App\Models\TikTokSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task #3: "Connect LinkedIn" — identity-only (no posting scope) via the
 * shared Connected Accounts hub that task #2 (YouTube/TikTok/Instagram)
 * will also use.
 */
class SocialConnectionControllerTest extends TestCase
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

    protected function enableLinkedIn(): void
    {
        LinkedInOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'client123', 'client_secret' => 'secret456'],
        ]);
    }

    public function test_the_page_reflects_whether_linkedin_connecting_is_available(): void
    {
        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('LinkedIn connections aren', false);

        $this->enableLinkedIn();

        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('Connect LinkedIn');
    }

    public function test_redirecting_to_an_unavailable_provider_fails_gracefully(): void
    {
        $this->actingAs($this->user)
            ->get(route('social-connections.redirect', 'linkedin'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_redirecting_to_an_unknown_provider_404s(): void
    {
        $this->actingAs($this->user)
            ->get(route('social-connections.redirect', 'facebook'))
            ->assertNotFound();
    }

    public function test_redirecting_sends_the_user_to_linkedins_authorization_screen(): void
    {
        $this->enableLinkedIn();

        $response = $this->actingAs($this->user)->get(route('social-connections.redirect', 'linkedin'));

        $response->assertRedirect();
        $this->assertStringContainsString('linkedin.com/oauth/v2/authorization', $response->headers->get('Location'));
    }

    public function test_the_callback_creates_a_connection_with_the_resolved_identity(): void
    {
        $this->enableLinkedIn();
        Http::fake([
            'linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'tok1']),
            'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'abc123', 'name' => 'Jordan Smith']),
        ]);

        $this->actingAs($this->user)->get(route('social-connections.redirect', 'linkedin'));
        $state = session('social_connect_state');

        $response = $this->actingAs($this->user)
            ->get(route('social-connections.callback', ['provider' => 'linkedin', 'state' => $state, 'code' => 'auth-code']));

        $response->assertRedirect(route('social-connections.index'));
        $response->assertSessionHas('success');

        $connection = SocialConnection::where('user_id', $this->user->id)->where('provider', 'linkedin')->firstOrFail();
        $this->assertSame('Jordan Smith', $connection->account_name);
        $this->assertSame('abc123', $connection->account_id);
        $this->assertNotNull($connection->connected_at);
    }

    public function test_the_callback_rejects_a_mismatched_state(): void
    {
        $this->enableLinkedIn();
        $this->actingAs($this->user)->get(route('social-connections.redirect', 'linkedin'));

        $response = $this->actingAs($this->user)
            ->get(route('social-connections.callback', ['provider' => 'linkedin', 'state' => 'wrong', 'code' => 'auth-code']));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('social_connections', 0);
    }

    public function test_a_user_can_disconnect(): void
    {
        SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'linkedin',
            'account_name' => 'Jordan Smith',
            'account_id' => 'abc123',
            'connected_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('social-connections.destroy', 'linkedin'))
            ->assertRedirect(route('social-connections.index'));

        $this->assertDatabaseCount('social_connections', 0);
    }

    public function test_an_isolated_plan_seat_can_reach_the_connected_accounts_page(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('social-connections.index'))->assertOk();
    }

    // --- Task #2: YouTube/TikTok/Instagram (the same generic hub/controller) ---

    protected function enableYoutube(bool $approved = false): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'youtube_publishing_enabled' => true,
            'youtube_approval_status' => $approved ? 'approved' : 'not_submitted',
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
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

    protected function enableInstagram(bool $approved = false): void
    {
        InstagramSetting::current()->update([
            'is_enabled' => true,
            'approval_status' => $approved ? 'approved' : 'not_submitted',
            'credentials' => ['app_id' => 'app123', 'app_secret' => 'secret456'],
        ]);
    }

    public function test_youtube_connecting_and_approval_state_show_on_the_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('YouTube connections aren', false);

        $this->enableYoutube(approved: false);
        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('Connect YouTube');
    }

    public function test_the_page_shows_awaiting_approval_for_a_connected_but_unapproved_youtube_channel(): void
    {
        $this->enableYoutube(approved: false);
        SocialConnection::create(['user_id' => $this->user->id, 'provider' => 'youtube', 'account_name' => 'Acme Channel', 'account_id' => 'chan1', 'connected_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('Awaiting YouTube', false);
    }

    public function test_the_page_shows_approved_for_a_connected_and_approved_tiktok_account(): void
    {
        $this->enableTikTok(approved: true);
        SocialConnection::create(['user_id' => $this->user->id, 'provider' => 'tiktok', 'account_name' => 'acme_official', 'account_id' => 'open1', 'connected_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('social-connections.index'))
            ->assertOk()
            ->assertSee('has approved AffilStack', false);
    }

    public function test_the_callback_creates_a_tiktok_connection(): void
    {
        $this->enableTikTok();
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open1', 'display_name' => 'acme_official']]]),
        ]);

        $this->actingAs($this->user)->get(route('social-connections.redirect', 'tiktok'));
        $state = session('social_connect_state');

        $response = $this->actingAs($this->user)
            ->get(route('social-connections.callback', ['provider' => 'tiktok', 'state' => $state, 'code' => 'auth-code']));

        $response->assertRedirect(route('social-connections.index'));
        $connection = SocialConnection::where('user_id', $this->user->id)->where('provider', 'tiktok')->firstOrFail();
        $this->assertSame('acme_official', $connection->account_name);
    }

    public function test_the_callback_creates_an_instagram_connection(): void
    {
        $this->enableInstagram();
        Http::fake([
            'graph.facebook.com/v19.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/v19.0/me/accounts*' => Http::response(['data' => [
                ['name' => 'Acme Page', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => 'ig1']],
            ]]),
            'graph.facebook.com/v19.0/ig1*' => Http::response(['username' => 'acme_official']),
        ]);

        $this->actingAs($this->user)->get(route('social-connections.redirect', 'instagram'));
        $state = session('social_connect_state');

        $response = $this->actingAs($this->user)
            ->get(route('social-connections.callback', ['provider' => 'instagram', 'state' => $state, 'code' => 'auth-code']));

        $response->assertRedirect(route('social-connections.index'));
        $connection = SocialConnection::where('user_id', $this->user->id)->where('provider', 'instagram')->firstOrFail();
        $this->assertSame('@acme_official', $connection->account_name);
    }

    public function test_a_user_can_disconnect_youtube(): void
    {
        SocialConnection::create(['user_id' => $this->user->id, 'provider' => 'youtube', 'account_name' => 'Acme Channel', 'account_id' => 'chan1', 'connected_at' => now()]);

        $this->actingAs($this->user)
            ->delete(route('social-connections.destroy', 'youtube'))
            ->assertRedirect(route('social-connections.index'));

        $this->assertDatabaseCount('social_connections', 0);
    }
}
