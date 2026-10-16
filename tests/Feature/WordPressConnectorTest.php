<?php

namespace Tests\Feature;

use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WordpressSite;
use App\Services\WordPress\ConnectorSignature;
use App\Services\WordPress\PluginPackager;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Feature 4: connecting WordPress sites through the AffilStack Connector
 * plugin — one-click approval, the server-to-server code exchange, plan
 * limits, the plugin download and its self-hosted updates.
 */
class WordPressConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function userWithSites(int $limit): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $plan = Plan::factory()->create(['wordpress_sites_limit' => $limit]);
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->fresh();
    }

    /**
     * @return array<string, string>
     */
    protected function connectRequest(string $host = 'myblog.example.com', array $overrides = []): array
    {
        return array_merge([
            'site_url' => "https://{$host}",
            'rest_url' => "https://{$host}/wp-json/",
            'return_url' => "https://{$host}/wp-admin/admin-post.php?action=affilstack_connect_return&_wpnonce=abc123",
            'state' => str_repeat('s', 40),
            'site_name' => 'My Blog',
        ], $overrides);
    }

    protected function approve(User $user, array $request): string
    {
        $response = $this->actingAs($user)->post(route('wordpress.approve'), $request)->assertOk();
        $returnUrl = $response->viewData('returnUrl');

        $this->assertStringStartsWith($request['return_url'].'&affilstack_code=', $returnUrl);
        parse_str((string) parse_url($returnUrl, PHP_URL_QUERY), $query);
        $this->assertSame($request['state'], $query['affilstack_state']);

        return $query['affilstack_code'];
    }

    public function test_approving_a_site_hands_the_plugin_a_one_time_code_it_swaps_for_the_secret(): void
    {
        $user = $this->userWithSites(3);
        $request = $this->connectRequest();

        $this->actingAs($user)->get(route('wordpress.connect', $request))
            ->assertOk()
            ->assertSee('My Blog')
            ->assertSee('Connect this site');

        $code = $this->approve($user, $request);
        $site = WordpressSite::sole();
        $this->assertSame('pending', $site->status);
        $this->assertNotSame($code, $site->connect_code_hash);

        $response = $this->postJson(route('api.wordpress.exchange'), [
            'code' => $code,
            'site_url' => 'https://myblog.example.com/',
            'name' => 'My Blog',
            'wp_version' => '6.8',
            'plugin_version' => '1.0.0',
            'seo_plugin' => 'yoast',
        ])->assertOk();

        $site->refresh();
        $this->assertSame('connected', $site->status);
        $this->assertSame($site->id, $response->json('site_id'));
        $this->assertSame($site->secret, $response->json('secret'));
        $this->assertSame('yoast', $site->seo_plugin);
        $this->assertNull($site->connect_code_hash);

        // The code works once.
        $this->postJson(route('api.wordpress.exchange'), ['code' => $code, 'site_url' => 'https://myblog.example.com'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'affilstack_invalid_code');
    }

    public function test_the_code_only_works_for_the_site_it_was_issued_to_and_expires(): void
    {
        $user = $this->userWithSites(3);
        $code = $this->approve($user, $this->connectRequest());

        $this->postJson(route('api.wordpress.exchange'), ['code' => $code, 'site_url' => 'https://attacker.example.com'])
            ->assertStatus(422);

        $this->travel(11)->minutes();

        $this->postJson(route('api.wordpress.exchange'), ['code' => $code, 'site_url' => 'https://myblog.example.com'])
            ->assertStatus(422);
        $this->assertSame('pending', WordpressSite::sole()->status);
    }

    public function test_the_code_can_only_be_sent_back_to_the_site_that_asked_for_it(): void
    {
        $user = $this->userWithSites(3);

        $this->actingAs($user)->get(route('wordpress.connect', $this->connectRequest(overrides: ['return_url' => 'https://evil.example.net/steal'])))
            ->assertOk()
            ->assertSee('doesn&#039;t match the site', false)
            ->assertDontSee('Connect this site');

        $this->actingAs($user)->post(route('wordpress.approve'), $this->connectRequest(overrides: ['return_url' => 'https://evil.example.net/steal']))
            ->assertRedirect(route('wordpress.index'));

        $this->actingAs($user)->post(route('wordpress.approve'), $this->connectRequest(overrides: ['return_url' => 'javascript:alert(1)']))
            ->assertRedirect(route('wordpress.index'));

        $this->assertSame(0, WordpressSite::count());
    }

    public function test_sites_on_private_networks_are_refused(): void
    {
        $user = $this->userWithSites(3);

        $this->actingAs($user)->post(route('wordpress.approve'), $this->connectRequest('localhost'))
            ->assertRedirect(route('wordpress.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, WordpressSite::count());
    }

    public function test_plans_without_the_plugin_cannot_connect_or_download(): void
    {
        $user = $this->userWithSites(0);

        $this->actingAs($user)->get(route('wordpress.connect', $this->connectRequest()))
            ->assertOk()
            ->assertSee("Your plan doesn't include the WordPress plugin", false)
            ->assertDontSee('Connect this site');

        $this->actingAs($user)->post(route('wordpress.approve'), $this->connectRequest())
            ->assertRedirect(route('wordpress.index'))
            ->assertSessionHas('error');

        $this->actingAs($user)->get(route('wordpress.download'))
            ->assertRedirect(route('wordpress.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, WordpressSite::count());
    }

    public function test_the_plan_limit_counts_connected_sites_but_reconnecting_one_is_free(): void
    {
        $user = $this->userWithSites(1);
        WordpressSite::factory()->for($user)->create(['site_url' => 'https://myblog.example.com']);

        $this->actingAs($user)->post(route('wordpress.approve'), $this->connectRequest('second.example.com'))
            ->assertRedirect(route('wordpress.index'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, '1 site'));

        // The same site reconnecting (new secret) doesn't count twice.
        $this->approve($user, $this->connectRequest('myblog.example.com'));
        $this->assertSame(1, WordpressSite::count());

        // Disconnected sites free their slot.
        WordpressSite::query()->update(['status' => 'disconnected']);
        $this->approve($user, $this->connectRequest('second.example.com'));
        $this->assertSame(2, WordpressSite::count());
    }

    public function test_unlimited_plans_have_no_site_limit(): void
    {
        $user = $this->userWithSites(-1);
        WordpressSite::factory()->for($user)->count(30)->create();

        $this->approve($user, $this->connectRequest('one-more.example.com'));

        $this->assertSame(31, WordpressSite::count());
    }

    public function test_only_the_account_owner_connects_and_manages_sites(): void
    {
        $owner = $this->userWithSites(3);
        $site = WordpressSite::factory()->for($owner)->create();
        $seat = User::factory()->create(['agency_owner_id' => $owner->id]);
        $seat->assignRole('user');

        $this->actingAs($seat->fresh())->post(route('wordpress.approve'), $this->connectRequest())->assertForbidden();
        $this->actingAs($seat->fresh())->delete(route('wordpress.sites.destroy', $site))->assertForbidden();

        $this->assertSame(1, WordpressSite::count());
        $this->assertSame('connected', $site->fresh()->status);
    }

    public function test_the_downloaded_plugin_carries_this_installations_address_and_version(): void
    {
        $user = $this->userWithSites(1);

        $response = $this->actingAs($user)->get(route('wordpress.download'))->assertOk();
        $this->assertStringContainsString('affilstack-connector.zip', (string) $response->headers->get('content-disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $main = (string) $zip->getFromName('affilstack-connector/affilstack-connector.php');
        $readme = (string) $zip->getFromName('affilstack-connector/readme.txt');

        $this->assertStringContainsString("define( 'AFFILSTACK_CONNECTOR_BASE_URL', '".PluginPackager::baseUrl()."' )", $main);
        $this->assertStringContainsString('Version:           '.config('wordpress_plugin.version'), $main);
        $this->assertStringContainsString('Stable tag: '.config('wordpress_plugin.version'), $readme);
        $this->assertNotFalse($zip->locateName('affilstack-connector/includes/class-affilstack-importer.php'));
        $this->assertNotFalse($zip->locateName('affilstack-connector/uninstall.php'));

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringNotContainsString('__AFFILSTACK_', (string) $zip->getFromIndex($i), $zip->getNameIndex($i));
        }

        $zip->close();
    }

    public function test_connected_sites_get_update_details_with_a_short_lived_download_link(): void
    {
        $user = $this->userWithSites(1);
        $site = WordpressSite::factory()->for($user)->create();

        $this->getJson(route('api.wordpress.plugin-info'))->assertUnauthorized();

        $headers = ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/plugin-info', '')
            + ['X-AffilStack-Plugin-Version' => '0.9.0'];

        // GET with an empty body, as WordPress sends it (getJson() would send "[]").
        $info = $this->withHeaders($headers + ['Accept' => 'application/json'])->get(route('api.wordpress.plugin-info'))->assertOk()->json();

        $this->assertSame(config('wordpress_plugin.version'), $info['version']);
        $this->assertSame('affilstack-connector', $info['slug']);
        $this->assertStringContainsString('signature=', $info['download_url']);
        $this->assertSame('0.9.0', $site->fresh()->plugin_version);

        $this->get($info['download_url'])->assertOk()->assertHeader('content-type', 'application/zip');

        // Tampered or expired links don't work.
        $this->get(str_replace('signature=', 'signature=x', $info['download_url']))->assertForbidden();
        $this->travel(7)->hours();
        $this->get($info['download_url'])->assertForbidden();
    }

    public function test_update_details_leave_out_the_download_when_the_plan_no_longer_includes_the_plugin(): void
    {
        $user = $this->userWithSites(0);
        $site = WordpressSite::factory()->for($user)->create();

        $info = $this->withHeaders(ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/plugin-info', '') + ['Accept' => 'application/json'])
            ->get(route('api.wordpress.plugin-info'))
            ->assertOk()
            ->json();

        $this->assertNull($info['download_url']);
    }

    public function test_signed_calls_need_the_right_secret_a_fresh_timestamp_and_the_same_request(): void
    {
        $site = WordpressSite::factory()->create();
        $call = fn (array $headers) => $this->withHeaders($headers + ['Accept' => 'application/json'])->get(route('api.wordpress.plugin-info'));

        $call(ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/plugin-info', ''))->assertOk();

        $call(ConnectorSignature::headers($site->id, 'wrong-secret', 'GET', '/api/wordpress/plugin-info', ''))->assertUnauthorized();
        $call(ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/plugin-info', '', time() - 600))->assertUnauthorized();
        $call(ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/other', ''))->assertUnauthorized();
        $call(ConnectorSignature::headers($site->id, $site->secret, 'POST', '/api/wordpress/plugin-info', ''))->assertUnauthorized();
        $call(ConnectorSignature::headers($site->id + 1, $site->secret, 'GET', '/api/wordpress/plugin-info', ''))->assertUnauthorized();

        $site->update(['status' => 'disconnected']);
        $call(ConnectorSignature::headers($site->id, $site->secret, 'GET', '/api/wordpress/plugin-info', ''))->assertUnauthorized();
    }

    public function test_disconnecting_inside_wordpress_disconnects_the_site_and_kills_its_secret(): void
    {
        $site = WordpressSite::factory()->create(['auto_send' => true]);
        $secret = $site->secret;
        $body = json_encode(['reason' => 'disconnected_in_wordpress']);

        $this->call('POST', route('api.wordpress.disconnect'), [], [], [], $this->transformHeadersToServerVars(
            ConnectorSignature::headers($site->id, $secret, 'POST', '/api/wordpress/disconnect', $body) + ['Content-Type' => 'application/json', 'Accept' => 'application/json']
        ), $body)->assertOk();

        $site->refresh();
        $this->assertSame('disconnected', $site->status);
        $this->assertFalse($site->auto_send);
        $this->assertNotSame($secret, $site->secret);
    }

    public function test_the_plugins_signature_code_matches_affilstacks(): void
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir().'/');
        }

        if (! function_exists('wp_parse_url')) {
            // WordPress's wrapper around parse_url(), for loading the plugin file outside WordPress.
            eval('function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }');
        }

        require_once PluginPackager::sourcePath().'/includes/class-affilstack-signature.php';

        $timestamp = time();
        $body = '{"title":"Hello — world"}';

        foreach (['/affilstack/v1/posts', 'affilstack/v1/posts/', '/api/wordpress/plugin-info'] as $path) {
            $this->assertSame(
                ConnectorSignature::sign('s3cret', $timestamp, 'post', $path, $body),
                \AffilStack_Signature::sign('s3cret', $timestamp, 'POST', $path, $body),
                $path,
            );
        }

        $headers = ConnectorSignature::headers(7, 's3cret', 'POST', '/affilstack/v1/posts', $body);
        $this->assertTrue(\AffilStack_Signature::verify('s3cret', $headers['X-AffilStack-Timestamp'], $headers['X-AffilStack-Signature'], 'POST', '/affilstack/v1/posts', $body));
        $this->assertFalse(\AffilStack_Signature::verify('s3cret', $headers['X-AffilStack-Timestamp'], $headers['X-AffilStack-Signature'], 'POST', '/affilstack/v1/posts', $body.' '));
    }

    public function test_the_sites_page_shows_the_plan_allowance_and_the_connect_steps(): void
    {
        $user = $this->userWithSites(3);
        WordpressSite::factory()->for($user)->create(['name' => 'Gadget Reviews']);

        $this->actingAs($user)->get(route('wordpress.index'))
            ->assertOk()
            ->assertSee('Gadget Reviews')
            ->assertSee('1 connected')
            ->assertSee('3 sites on your plan')
            ->assertSee('Download the plugin');
    }

    public function test_customers_can_only_manage_their_own_sites(): void
    {
        $user = $this->userWithSites(3);
        $other = WordpressSite::factory()->create();

        $this->actingAs($user)->patch(route('wordpress.sites.update', $other), ['auto_send' => 1])->assertNotFound();
        $this->actingAs($user)->delete(route('wordpress.sites.destroy', $other))->assertNotFound();
        $this->assertFalse((bool) $other->fresh()->auto_send);
        $this->assertSame('connected', $other->fresh()->status);
    }

    public function test_admins_can_set_the_wordpress_allowance_on_a_plan(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plan = Plan::factory()->create();
        $this->assertSame(0, $plan->fresh()->wordpress_sites_limit);

        Livewire::actingAs($admin)
            ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->assertFormFieldExists('wordpress_sites_limit')
            ->fillForm(['wordpress_sites_limit' => -1])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(-1, $plan->fresh()->wordpress_sites_limit);
    }
}
