<?php

namespace Tests\Feature;

use App\Filament\Pages\MarketplaceOptions;
use App\Filament\Resources\AddonReviews\AddonReviewResource;
use App\Filament\Resources\AddonReviews\Pages\ViewAddonReview;
use App\Filament\Resources\Addons\Pages\ListAddons;
use App\Filament\Resources\DeveloperEarnings\Pages\ListDeveloperEarnings;
use App\Filament\Resources\Developers\Pages\ListDevelopers;
use App\Http\Controllers\Developer\PortalController;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\DeveloperProfile;
use App\Models\User;
use App\Notifications\AddonReviewed;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\MarketplaceTestHelpers;
use Tests\TestCase;

/**
 * Feature 7: developers register with their AffilStack account, build an
 * add-on as a draft, submit it, and an admin approves it before customers
 * see it.
 */
class DeveloperPortalTest extends TestCase
{
    use MarketplaceTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMarketplaceRoles();
        Storage::fake('public');
    }

    public function test_the_portal_home_and_every_docs_page_are_public(): void
    {
        $this->get(route('developer.home'))->assertOk()->assertSee('Build what affiliate marketers need next.')->assertSee('70% to you');

        foreach (array_keys(PortalController::DOCS) as $page) {
            $this->get(route('developer.docs', $page))->assertOk()->assertSee(PortalController::DOCS[$page]);
        }

        $this->get(route('developer.docs', 'nope'))->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in_with_their_affilstack_account(): void
    {
        $this->get(route('developer.dashboard'))->assertRedirect(route('developer.login'));
        $this->get(route('developer.login'))->assertRedirect(route('login'));
    }

    public function test_a_customer_registers_as_a_developer_after_accepting_the_terms(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('developer.dashboard'))->assertRedirect(route('developer.join'));
        $this->actingAs($user)->get(route('developer.join'))->assertOk()->assertSee('Developer terms');

        $this->actingAs($user)->post(route('developer.join.store'), ['display_name' => 'Acme Labs', 'support_email' => 'help@acme.example'])
            ->assertSessionHasErrors('accept_terms');

        $this->actingAs($user)->post(route('developer.join.store'), ['display_name' => 'Acme Labs', 'support_email' => 'help@acme.example', 'accept_terms' => '1'])
            ->assertRedirect(route('developer.dashboard'));

        $profile = $user->fresh()->developerProfile;
        $this->assertSame('acme-labs', $profile->slug);
        $this->assertNotNull($profile->terms_accepted_at);
        $this->assertSame(70.0, $profile->sharePercent());

        $this->actingAs($user->fresh())->get(route('developer.dashboard'))->assertOk()->assertSee('Create your first add-on');
    }

    public function test_a_suspended_developer_cannot_use_the_portal(): void
    {
        $developer = $this->developer(['status' => 'suspended']);

        $this->actingAs($developer)->get(route('developer.dashboard'))->assertForbidden();
    }

    public function test_creating_an_app_issues_credentials_and_shows_the_secret_once(): void
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->post(route('developer.addons.store'), ['type' => 'app', 'name' => 'Rank Booster']);

        $addon = Addon::where('slug', 'rank-booster')->firstOrFail();
        $response->assertRedirect(route('developer.addons.edit', $addon))->assertSessionHas('client_secret');

        $secret = session('client_secret');
        $this->assertStringStartsWith('afapp_', $addon->client_id);
        $this->assertTrue($addon->fresh()->secretMatches($secret));
        $this->assertStringStartsWith('whsec_', $addon->fresh()->webhook_secret);
        $this->assertSame('draft', $addon->versions()->sole()->status);

        $this->actingAs($developer)->get(route('developer.addons.edit', $addon))->assertOk()->assertSee($secret);
        $this->actingAs($developer)->get(route('developer.addons.show', $addon))->assertOk()->assertSee($addon->client_id)->assertDontSee($secret);
    }

    public function test_an_incomplete_listing_cannot_be_submitted(): void
    {
        $developer = $this->developer();
        $this->actingAs($developer)->post(route('developer.addons.store'), ['type' => 'app', 'name' => 'Rank Booster']);
        $addon = Addon::firstOrFail();

        $this->actingAs($developer)->get(route('developer.addons.show', $addon))->assertOk()->assertSee('Upload an icon.');

        $this->actingAs($developer)->post(route('developer.addons.submit', $addon))->assertSessionHasErrors('version');
        $this->assertSame('draft', $this->versionOf($addon)->status);
    }

    public function test_a_developer_fills_in_an_app_listing_and_submits_it(): void
    {
        $developer = $this->developer();
        $admin = $this->admin();
        $this->actingAs($developer)->post(route('developer.addons.store'), ['type' => 'app', 'name' => 'Rank Booster']);
        $addon = Addon::firstOrFail();

        $this->actingAs($developer)->put(route('developer.addons.update', $addon), [
            'version' => '1.0.0',
            'name' => 'Rank Booster',
            'tagline' => 'Track where your reviews rank.',
            'description' => str_repeat('Rank Booster watches your review articles and tells you when they move. ', 2),
            'category' => 'analytics',
            'pricing' => 'monthly',
            'price' => '9.99',
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
            'screenshots' => [UploadedFile::fake()->image('shot.png', 1200, 800)],
            'install_url' => 'https://rankbooster.example/connect',
            'privacy_url' => 'https://rankbooster.example/privacy',
            'redirect_uris' => "https://rankbooster.example/callback\nhttp://localhost:3000/callback",
            'scopes' => ['profile:read', 'offers:read', 'not-a-scope'],
            'webhook_url' => 'https://rankbooster.example/hooks',
            'webhook_events' => ['generation.completed'],
            'action' => 'submit',
        ])->assertSessionHasErrors('scopes.2');

        $this->actingAs($developer)->put(route('developer.addons.update', $addon), [
            'version' => '1.0.0',
            'name' => 'Rank Booster',
            'tagline' => 'Track where your reviews rank.',
            'description' => str_repeat('Rank Booster watches your review articles and tells you when they move. ', 2),
            'category' => 'analytics',
            'pricing' => 'monthly',
            'price' => '9.99',
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
            'screenshots' => [UploadedFile::fake()->image('shot.png', 1200, 800)],
            'install_url' => 'https://rankbooster.example/connect',
            'privacy_url' => 'https://rankbooster.example/privacy',
            'redirect_uris' => "https://rankbooster.example/callback\nhttp://localhost:3000/callback",
            'scopes' => ['profile:read', 'offers:read'],
            'webhook_url' => 'https://rankbooster.example/hooks',
            'webhook_events' => ['generation.completed'],
            'action' => 'submit',
        ])->assertRedirect(route('developer.addons.show', $addon));

        $version = $this->versionOf($addon);
        $this->assertSame('submitted', $version->status);
        $this->assertSame(999, $version->price_cents);
        $this->assertSame(['https://rankbooster.example/callback', 'http://localhost:3000/callback'], $version->redirect_uris);
        $this->assertSame(['profile:read', 'offers:read'], $version->scopes);
        Storage::disk('public')->assertExists($version->icon_path);
        $this->assertCount(1, $version->screenshots);

        // Admins hear about it; customers can't see it yet.
        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->get(route('marketplace.show', $addon))->assertNotFound();

        // While in review the draft can't be edited, only withdrawn.
        $this->actingAs($developer)->get(route('developer.addons.edit', $addon))->assertRedirect(route('developer.addons.show', $addon));
        $this->actingAs($developer)->post(route('developer.addons.withdraw', $addon))->assertRedirect(route('developer.addons.edit', $addon));
        $this->assertSame('draft', $version->fresh()->status);
    }

    public function test_redirect_urls_must_be_https_or_localhost(): void
    {
        $developer = $this->developer();
        $this->actingAs($developer)->post(route('developer.addons.store'), ['type' => 'app', 'name' => 'Rank Booster']);
        $addon = Addon::firstOrFail();

        $this->actingAs($developer)->put(route('developer.addons.update', $addon), [
            'version' => '1.0.0', 'name' => 'Rank Booster', 'category' => 'analytics', 'pricing' => 'free',
            'redirect_uris' => "http://rankbooster.example/callback\njavascript:alert(1)",
        ])->assertSessionHasErrors('redirect_uris');
    }

    public function test_another_developers_add_on_is_not_found(): void
    {
        $owner = $this->developer();
        $addon = $this->liveAddon($owner);
        $other = $this->developer(['slug' => 'other']);

        $this->actingAs($other)->get(route('developer.addons.show', $addon))->assertNotFound();
        $this->actingAs($other)->put(route('developer.addons.update', $addon), [])->assertNotFound();
        $this->actingAs($other)->post(route('developer.addons.secret', $addon))->assertNotFound();
    }

    public function test_an_admin_approves_a_version_and_it_goes_live(): void
    {
        Notification::fake();
        $developer = $this->developer();
        $admin = $this->admin();
        $addon = Addon::create(['developer_profile_id' => $developer->developerProfile->id, 'slug' => 'swipe-pack', 'type' => 'template_pack', 'status' => 'draft']);
        $version = $addon->versions()->create(array_merge(['version' => '1.0.0', 'status' => 'submitted', 'name' => 'Swipe Pack', 'tagline' => 'Hooks that convert', 'description' => str_repeat('x', 90), 'category' => 'content', 'icon_path' => 'i.png', 'pricing' => 'free', 'submitted_at' => now()], $this->typeDefaults('template_pack')));

        Livewire::actingAs($admin)->test(ViewAddonReview::class, ['record' => $version->getRouteKey()])
            ->assertSee('Cold email: first touch')
            ->callAction('approve', ['note' => 'Nice work'])
            ->assertHasNoActionErrors();

        $addon->refresh();
        $this->assertSame('published', $addon->status);
        $this->assertSame($version->id, $addon->live_version_id);
        $this->assertSame('approved', $version->fresh()->status);
        $this->assertSame($admin->id, $version->fresh()->reviewed_by_id);
        Notification::assertSentTo($developer, AddonReviewed::class);

        $this->get(route('marketplace.index'))->assertOk()->assertSee('Swipe Pack');
        $this->get(route('marketplace.show', $addon))->assertOk()->assertSee('Hooks that convert');
    }

    public function test_sending_a_version_back_needs_a_note_the_developer_sees(): void
    {
        Notification::fake();
        $developer = $this->developer();
        $addon = $this->liveAddon($developer);
        $draft = $addon->versions()->create(array_merge($addon->liveVersion->only((new AddonVersion)->getFillable()), ['version' => '1.0.1', 'status' => 'submitted', 'submitted_at' => now()]));

        Livewire::actingAs($this->admin())->test(ViewAddonReview::class, ['record' => $draft->getRouteKey()])
            ->callAction('reject', ['note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        Livewire::actingAs($this->admin())->test(ViewAddonReview::class, ['record' => $draft->getRouteKey()])
            ->callAction('reject', ['note' => 'Explain why you need CRM access.'])
            ->assertHasNoActionErrors();

        $this->assertSame('rejected', $draft->fresh()->status);
        $this->assertSame($addon->liveVersion->id, $addon->fresh()->live_version_id);
        Notification::assertSentTo($developer, AddonReviewed::class);

        $this->actingAs($developer)->get(route('developer.addons.show', $addon))->assertSee('Explain why you need CRM access.');
        $this->actingAs($developer)->get(route('developer.addons.edit', $addon))->assertOk();
    }

    public function test_editing_a_live_add_on_starts_a_new_draft_version(): void
    {
        $developer = $this->developer();
        $addon = $this->liveAddon($developer);

        $this->actingAs($developer)->get(route('developer.addons.edit', $addon))->assertOk()->assertSee('1.0.1');

        $draft = $addon->workingVersion()->first();
        $this->assertSame('1.0.1', $draft->version);
        $this->assertSame('draft', $draft->status);
        $this->assertSame($addon->liveVersion->scopes, $draft->scopes);
        $this->assertSame('approved', $addon->liveVersion->fresh()->status);
    }

    public function test_retiring_stops_renewals_and_unlists_the_add_on(): void
    {
        $developer = $this->developer();
        $addon = $this->liveAddon($developer, 'template_pack', ['pricing' => 'monthly', 'price_cents' => 500]);
        $install = $addon->installs()->create(['user_id' => $this->customer()->id, 'status' => 'active', 'price_cents' => 500, 'current_period_end' => now()->addWeek()]);

        $this->actingAs($developer)->post(route('developer.addons.retire', $addon))->assertRedirect();

        $this->assertSame('retired', $addon->fresh()->status);
        $this->assertTrue($install->fresh()->cancel_at_period_end);
        $this->get(route('marketplace.show', $addon))->assertNotFound();
    }

    public function test_admin_marketplace_screens_load_and_settings_save(): void
    {
        $admin = $this->admin();
        $this->liveAddon($this->developer());

        Livewire::actingAs($admin)->test(ListAddons::class)->assertOk()->assertSee('Rank Booster');
        Livewire::actingAs($admin)->test(ListDevelopers::class)->assertOk()->assertSee('Acme Labs');
        Livewire::actingAs($admin)->test(ListDeveloperEarnings::class)->assertOk();

        Livewire::actingAs($admin)->test(MarketplaceOptions::class)
            ->fillForm(['enabled' => false, 'revenue_share' => 80, 'hold_days' => 7, 'terms' => 'Be nice.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(MarketplaceSettings::enabled());
        $this->assertSame(80.0, MarketplaceSettings::revenueSharePercent());
        $this->assertSame(7, MarketplaceSettings::holdDays());
        $this->assertSame('Be nice.', MarketplaceSettings::terms());
        $this->get(route('marketplace.index'))->assertNotFound();
    }

    public function test_a_developer_can_have_their_own_revenue_share(): void
    {
        $developer = $this->developer(['revenue_share_percent' => 85]);

        $this->assertSame(85.0, DeveloperProfile::where('user_id', $developer->id)->first()->sharePercent());
    }

    public function test_marketplace_admin_screens_need_the_marketplace_department(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('admin_sub');

        $this->assertFalse(AddonReviewResource::canAccess());

        $this->actingAs($staff);
        $this->assertFalse(AddonReviewResource::canAccess());

        $staff->givePermissionTo('department.marketplace');
        $this->assertTrue(AddonReviewResource::canAccess());
    }
}
