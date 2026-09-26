<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTickets\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsurePanelLoginCompleted;
use App\Jobs\SendWebhookDelivery;
use App\Models\ApiToken;
use App\Models\BingWebmasterSetting;
use App\Models\CrmContact;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for the pre-launch authorization / injection review
 * (Sept 2026): each test pins one loophole that existed before the fix.
 */
class LaunchHardeningSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_a_suspended_accounts_api_token_stops_working(): void
    {
        $user = $this->customer();
        $token = ApiToken::generate($user, 'CLI')['plainText'];

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk();

        $user->update(['is_suspended' => true]);

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertForbidden();
        $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_a_read_only_token_cannot_create_research_clips_through_the_extension_api(): void
    {
        $token = ApiToken::generate($this->customer(), 'Read only', scope: 'read_only')['plainText'];

        $this->postJson('/api/clips', ['url' => 'https://example.com', 'title' => 'x', 'content' => 'y'], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();

        $this->assertDatabaseCount('research_clips', 0);
    }

    public function test_a_deleted_accounts_token_gets_a_clean_401_instead_of_an_error(): void
    {
        $user = $this->customer();
        $token = ApiToken::generate($user, 'CLI')['plainText'];
        ApiToken::query()->update(['user_id' => $user->id]);
        $user->delete();

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_webhook_urls_pointing_at_private_addresses_are_rejected(): void
    {
        $user = $this->customer();

        foreach (['http://127.0.0.1:3306/', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/hook', 'http://localhost/hook', 'http://[::1]/hook'] as $url) {
            $this->assertNotNull(OutboundUrlGuard::problem($url), "{$url} should be refused");
        }

        OutboundUrlGuard::resolveUsing(fn () => ['192.168.1.20']);

        $this->actingAs($user)->post(route('api-access.webhooks.store'), [
            'url' => 'https://internal.example.com/hook',
            'events' => ['crm_contact.created'],
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('webhook_endpoints', 0);
    }

    public function test_a_webhook_host_re_pointed_at_a_private_address_is_blocked_at_delivery(): void
    {
        $user = $this->customer();
        $endpoint = WebhookEndpoint::create([
            'user_id' => $user->id, 'url' => 'https://hooks.example.com/in', 'secret' => 'whsec', 'events' => ['crm_contact.created'], 'is_active' => true,
        ]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id, 'event' => 'crm_contact.created', 'payload' => ['id' => 1], 'status' => 'pending', 'attempts' => 0,
        ]);

        // DNS now answers with a loopback address (rebinding).
        OutboundUrlGuard::resolveUsing(fn () => ['127.0.0.1']);
        Http::fake();

        (new SendWebhookDelivery($delivery))->handle();

        Http::assertNothingSent();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringStartsWith('Blocked:', $delivery->fresh()->response_body);
    }

    public function test_an_admin_session_that_skipped_the_panel_login_is_signed_out(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // e.g. signed in through the customer /login form or Google, which
        // never ran the panel's two-factor challenge.
        $this->actingAs($admin)->get('/afs-admin')->assertRedirect('/afs-admin/login');
        $this->assertGuest();

        // Through the panel's own login: stays signed in (Filament then
        // sends a first-time admin on to set up two-factor).
        $response = $this->actingAs($admin)
            ->withSession([EnsurePanelLoginCompleted::SESSION_KEY => $admin->id])
            ->get('/afs-admin');

        $this->assertNotSame(url('/afs-admin/login'), $response->headers->get('Location'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_only_the_super_admin_can_see_other_staff_in_the_users_resource(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(['admin', 'super-admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = $this->customer();

        $this->actingAs($admin);
        $visibleToAdmin = UserResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($visibleToAdmin->contains($customer->id));
        $this->assertFalse($visibleToAdmin->contains($owner->id), 'an admin must not be able to edit the super-admin');

        $this->actingAs($owner);
        $this->assertTrue(UserResource::getEloquentQuery()->pluck('id')->contains($admin->id));
    }

    public function test_site_page_content_is_sanitized_when_rendered(): void
    {
        SitePage::create([
            'slug' => 'terms', 'title' => 'Terms', 'is_published' => true,
            'content' => '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">x</a>',
        ]);

        $html = $this->get('/terms')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Hello</p>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:alert(3)', $html);
    }

    public function test_script_urls_in_the_navigation_menu_are_never_rendered(): void
    {
        SiteSetting::set('menu_items', json_encode([
            ['label' => 'Pricing', 'url' => '/pricing'],
            ['label' => 'Evil', 'url' => 'javascript:alert(document.cookie)'],
        ]));

        $html = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('>Pricing<', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_crm_exports_neutralize_spreadsheet_formulas(): void
    {
        $user = $this->customer();
        CrmContact::create(['user_id' => $user->id, 'name' => '=HYPERLINK("https://evil.test","click")', 'status' => 'new', 'source' => 'google_maps']);

        $csv = $this->actingAs($user)->get(route('crm.export'))->assertOk()->getContent();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
    }

    public function test_generations_left_unfinished_by_a_dead_worker_are_marked_failed(): void
    {
        $user = $this->customer();
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Widget', 'product_url' => 'https://example.com', 'affiliate_network' => 'Impact', 'status' => 'ready']);
        $stuck = Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'queued', 'credits_spent' => 0]);
        $fresh = Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'queued', 'credits_spent' => 0]);
        DB::table('generations')->where('id', $stuck->id)->update(['updated_at' => now()->subHour()]);

        $this->artisan('generations:fail-stuck')->assertSuccessful();

        $this->assertSame('failed', $stuck->fresh()->status);
        $this->assertSame('queued', $fresh->fresh()->status);
    }

    public function test_the_admin_api_clamps_page_size_and_never_returns_payout_details(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = ApiToken::generate($admin, 'Reporting', type: 'admin')['plainText'];
        $customer = $this->customer();
        $customer->update(['payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'private@example.com']]);

        $response = $this->getJson('/api/v1/admin/users?per_page=-1', ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertSame(1, $response->json('per_page'));
        $this->assertStringNotContainsString('private@example.com', $response->getContent());
    }

    public function test_singleton_settings_survive_an_auto_increment_that_is_not_one(): void
    {
        // MariaDB/MySQL never reuse an id consumed by a rolled-back insert.
        DB::table('bing_webmaster_settings')->insert(['id' => 7, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(7, BingWebmasterSetting::current()->id);
        $this->assertTrue(BingWebmasterSetting::current()->is_enabled);
        $this->assertSame(1, BingWebmasterSetting::count());
    }

    public function test_staff_can_reply_to_support_tickets_from_the_ticket_view(): void
    {
        $this->assertFalse((new MessagesRelationManager)->isReadOnly());
    }
}
