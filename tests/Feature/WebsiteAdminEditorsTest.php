<?php

namespace Tests\Feature;

use App\Console\Commands\Dev\SeedDemoData;
use App\Filament\Pages\FaqPageEditor;
use App\Filament\Pages\FeaturesOverviewEditor;
use App\Filament\Pages\HelpCentreEditor;
use App\Filament\Pages\HomepageEditor;
use App\Filament\Pages\PricingPageEditor;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\HomepageFeaturesSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website → … editors: every box opens with the words that are live, saving
 * changes the public page, and the homepage "How it works" steps show their
 * screenshots.
 */
class WebsiteAdminEditorsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_copy_editors_open_prefilled_with_the_live_text(): void
    {
        Livewire::actingAs($this->admin)->test(PricingPageEditor::class)->assertOk()->assertSet('data.pricing_featured_label', 'Most popular');
        Livewire::actingAs($this->admin)->test(FaqPageEditor::class)->assertOk()->assertSet('data.faq_heading', 'Frequently asked questions');
        Livewire::actingAs($this->admin)->test(HelpCentreEditor::class)->assertOk()->assertSet('data.help_chat_title', 'Chat with support');
        Livewire::actingAs($this->admin)->test(FeaturesOverviewEditor::class)->assertOk()->assertSet('data.feature_steps_heading', 'How to use it');
        Livewire::actingAs($this->admin)->test(HomepageEditor::class)->assertSet('data.home_how_eyebrow', 'How it works')->assertSet('data.hero_headline', 'Find the offer. Find the buyer. *Promote it everywhere.*');
    }

    public function test_saving_the_pricing_page_editor_changes_the_public_page_and_keeps_unchanged_copy_on_the_default(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'is_active' => true, 'is_featured' => true]);

        Livewire::actingAs($this->admin)
            ->test(PricingPageEditor::class)
            ->fillForm(['pricing_heading' => 'Plans that *pay for themselves*', 'pricing_button_label' => 'Choose this plan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', SiteSetting::get('pricing_featured_label'));

        auth()->logout();
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Plans that <span', false)
            ->assertSee('Choose this plan')
            ->assertSee('Most popular');
    }

    public function test_help_and_features_overview_edits_reach_the_site(): void
    {
        Livewire::actingAs($this->admin)->test(HelpCentreEditor::class)->fillForm(['help_ticket_title' => 'Raise a ticket'])->call('save')->assertHasNoFormErrors();
        Livewire::actingAs($this->admin)->test(FeaturesOverviewEditor::class)->fillForm(['features_heading' => 'All the *tools*'])->call('save')->assertHasNoFormErrors();

        auth()->logout();
        $this->get('/help')->assertSee('Raise a ticket');
        $this->get('/features')->assertSee('All the <span', false);
    }

    public function test_homepage_how_it_works_steps_show_their_screenshots_and_admin_edits(): void
    {
        $this->get('/')
            ->assertSee(asset('images/features/offer-research.webp'), false)
            ->assertSee(asset('images/features/earnings.webp'), false)
            ->assertSee('role="tablist"', false);

        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm(['home_how_eyebrow' => 'The journey', 'home_features_link_label' => 'Read more'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', SiteSetting::get('home_how_heading'));

        auth()->logout();
        $this->get('/')->assertSee('The journey');
    }

    public function test_homepage_feature_cards_show_their_built_in_screenshot_and_link(): void
    {
        $this->seed(HomepageFeaturesSeeder::class);

        $this->get('/')
            ->assertSee(asset('images/features/local-leads.webp'), false)
            ->assertSee('href="/features/google-maps-lead-finder"', false);
    }

    public function test_the_demo_data_command_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('affilstack:demo-data')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => SeedDemoData::EMAIL]);
    }
}
