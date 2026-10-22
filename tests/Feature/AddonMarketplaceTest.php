<?php

namespace Tests\Feature;

use App\Models\AddonInstall;
use App\Models\UserTemplate;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Marketplace\AddonInstaller;
use App\Services\Marketplace\AddonReviewService;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MarketplaceTestHelpers;
use Tests\TestCase;

/**
 * Feature 7: customers browse approved add-ons, add free ones, use template
 * packs and automation recipes, and remove them again.
 */
class AddonMarketplaceTest extends TestCase
{
    use MarketplaceTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMarketplaceRoles();
    }

    public function test_only_approved_add_ons_from_active_developers_are_listed(): void
    {
        $developer = $this->developer();
        $live = $this->liveAddon($developer, 'app', ['name' => 'Rank Booster']);
        $suspended = $this->liveAddon($developer, 'template_pack', ['name' => 'Banned Pack'], 'suspended');
        $hidden = $this->liveAddon($this->developer(['slug' => 'gone', 'status' => 'suspended']), 'automation', ['name' => 'Hidden Recipe']);

        $this->get(route('marketplace.index'))->assertOk()->assertSee('Rank Booster')->assertDontSee('Banned Pack')->assertDontSee('Hidden Recipe');
        $this->get(route('marketplace.show', $live))->assertOk()->assertSee('See your name, email address and plan');
        $this->get(route('marketplace.show', $suspended))->assertNotFound();
        $this->get(route('marketplace.show', $hidden))->assertNotFound();

        $customer = $this->customer();
        $this->actingAs($customer)->get(route('marketplace.browse'))->assertOk()->assertSee('Rank Booster');
        $this->actingAs($customer)->get(route('marketplace.addon', $suspended))->assertNotFound();
    }

    public function test_the_sitemap_lists_only_listed_add_ons(): void
    {
        $developer = $this->developer();
        $live = $this->liveAddon($developer, 'app', ['name' => 'Rank Booster']);
        $suspended = $this->liveAddon($developer, 'template_pack', ['name' => 'Banned Pack'], 'suspended');

        $this->get(route('seo.sitemap'))->assertOk()
            ->assertSee(route('marketplace.show', $live), false)
            ->assertDontSee(route('marketplace.show', $suspended), false);
    }

    public function test_the_marketplace_can_be_searched_and_filtered(): void
    {
        $developer = $this->developer();
        $this->liveAddon($developer, 'app', ['name' => 'Rank Booster', 'category' => 'analytics']);
        $this->liveAddon($developer, 'template_pack', ['name' => 'Hook Vault', 'category' => 'content']);

        $this->get(route('marketplace.index', ['q' => 'hook']))->assertSee('Hook Vault')->assertDontSee('Rank Booster');
        $this->get(route('marketplace.index', ['type' => 'app']))->assertSee('Rank Booster')->assertDontSee('Hook Vault');
        $this->get(route('marketplace.index', ['category' => 'content']))->assertSee('Hook Vault')->assertDontSee('Rank Booster');
    }

    public function test_adding_a_free_template_pack_fills_the_template_library(): void
    {
        $pack = $this->liveAddon($this->developer(), 'template_pack', ['name' => 'Hook Vault']);
        $customer = $this->customer();

        $response = $this->actingAs($customer)->post(route('marketplace.install', $pack));

        $install = AddonInstall::where('user_id', $customer->id)->sole();
        $response->assertRedirect(route('addons.show', $install));
        $this->assertSame('active', $install->status);
        $this->assertSame(1, $pack->fresh()->installs_count);
        $this->assertSame(2, UserTemplate::where('user_id', $customer->id)->count());

        $this->actingAs($customer)->get(route('templates.index'))->assertOk()->assertSee('Cold email: first touch')->assertSee('Curiosity hook');
        $this->actingAs($customer)->get(route('templates.index', ['kind' => 'hook']))->assertSee('Curiosity hook')->assertDontSee('Cold email: first touch');
        $this->actingAs($customer)->get(route('addons.index'))->assertOk()->assertSee('Hook Vault');

        // Adding it again doesn't duplicate anything.
        $this->actingAs($customer)->post(route('marketplace.install', $pack));
        $this->assertSame(2, UserTemplate::where('user_id', $customer->id)->count());
        $this->assertSame(1, $pack->fresh()->installs_count);

        $this->actingAs($customer)->delete(route('addons.destroy', $install))->assertRedirect(route('addons.index'));
        $this->assertSame('uninstalled', $install->fresh()->status);
        $this->assertSame(0, UserTemplate::where('user_id', $customer->id)->count());
        $this->actingAs($customer)->get(route('addons.show', $install))->assertNotFound();
    }

    public function test_an_automation_recipe_shows_its_steps_and_blueprint_to_its_owner_only(): void
    {
        $recipe = $this->liveAddon($this->developer(), 'automation', ['name' => 'Leads to CRM']);
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('marketplace.install', $recipe));
        $install = AddonInstall::where('user_id', $customer->id)->sole();

        $this->actingAs($customer)->get(route('addons.show', $install))->assertOk()->assertSee('Paste your AffilStack API key')->assertSee('https://zapier.com/shared/abc');

        $download = $this->actingAs($customer)->get(route('addons.blueprint', $install))->assertOk();
        $this->assertSame('{"zap":"lead-to-crm"}', $download->getContent());
        $this->assertStringContainsString('attachment; filename="lead-to-crm.json"', (string) $download->headers->get('Content-Disposition'));

        $this->actingAs($this->customer())->get(route('addons.blueprint', $install))->assertNotFound();
    }

    public function test_a_paid_add_on_cannot_be_added_without_paying(): void
    {
        $pack = $this->liveAddon($this->developer(), 'template_pack', ['pricing' => 'monthly', 'price_cents' => 900]);
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('marketplace.install', $pack))->assertRedirect(route('marketplace.addon', $pack));

        $install = AddonInstall::where('user_id', $customer->id)->sole();
        $this->assertSame('pending_payment', $install->status);
        $this->assertFalse($install->isUsable());
        $this->assertSame(0, UserTemplate::count());
        $this->assertSame(0, $pack->fresh()->installs_count);
    }

    public function test_a_developer_can_try_their_own_unapproved_add_on_for_free(): void
    {
        $developer = $this->developer();
        $addon = app(AddonReviewService::class)->create($developer->developerProfile, 'template_pack', 'Draft Pack');
        $addon->workingVersion->update(['content' => $this->typeDefaults('template_pack')['content'], 'pricing' => 'monthly', 'price_cents' => 900]);

        $this->actingAs($developer)->get(route('marketplace.addon', $addon))->assertOk()->assertSee('before it\'s approved', false);
        $this->actingAs($developer)->post(route('marketplace.install', $addon));
        $this->assertSame(2, UserTemplate::where('user_id', $developer->id)->count());

        $this->actingAs($this->customer())->get(route('marketplace.addon', $addon))->assertNotFound();
    }

    public function test_connecting_an_app_starts_on_the_apps_own_website(): void
    {
        $app = $this->liveAddon($this->developer());

        $this->actingAs($this->customer())->post(route('marketplace.install', $app))->assertRedirect('https://rankbooster.example/connect');
        $this->assertSame(0, AddonInstall::count());
    }

    public function test_an_apps_webhook_receives_only_the_events_the_customer_allowed(): void
    {
        Queue::fake();
        $app = $this->liveAddon($this->developer(), 'app', [
            'webhook_url' => 'https://rankbooster.example/hooks',
            'webhook_events' => ['generation.completed', 'crm_contact.created'],
        ]);
        $app->issueClientCredentials();
        $customer = $this->customer();

        $install = app(AddonInstaller::class)->install($customer, $app->fresh(), ['profile:read', 'crm:read']);

        $endpoint = WebhookEndpoint::where('addon_install_id', $install->id)->sole();
        $this->assertSame(['crm_contact.created'], $endpoint->events);
        $this->assertSame($app->fresh()->webhook_secret, $endpoint->secret);

        app(WebhookDispatcher::class)->dispatch($customer, 'crm_contact.created', ['id' => 1]);
        app(WebhookDispatcher::class)->dispatch($customer, 'generation.completed', ['id' => 2]);
        $this->assertSame(1, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->count());

        // It's managed by the app, not listed with the customer's own webhooks.
        $this->actingAs($customer)->get(route('api-access.index'))->assertOk()->assertDontSee('https://rankbooster.example/hooks');

        app(AddonInstaller::class)->uninstall($install);
        $this->assertSame(0, WebhookEndpoint::where('addon_install_id', $install->id)->count());
    }

    public function test_approving_a_version_that_drops_a_permission_takes_it_back_from_customers(): void
    {
        $developer = $this->developer();
        $app = $this->liveAddon($developer);
        $install = app(AddonInstaller::class)->install($this->customer(), $app, ['profile:read', 'crm:read']);
        $token = $install->apiTokens()->create(['user_id' => $install->user_id, 'type' => 'app', 'scope' => 'read_only', 'scopes' => ['profile:read', 'crm:read'], 'name' => 'App', 'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addHour()]);

        $draft = app(AddonReviewService::class)->draftFor($app);
        $draft->update(['scopes' => ['profile:read'], 'status' => 'submitted']);
        app(AddonReviewService::class)->approve($draft, $this->admin());

        $this->assertSame(['profile:read'], $install->fresh()->scopes);
        $this->assertSame(['profile:read'], $token->fresh()->scopes);
    }
}
