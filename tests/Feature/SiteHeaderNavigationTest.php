<?php

namespace Tests\Feature;

use App\Filament\Pages\NavigationMenuEditor;
use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public header: Tools / Resources / Pricing in the centre and Talk To
 * An Expert / Login / Get Started on the right, with every label editable
 * in Website → Navigation menu.
 */
class SiteHeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_login_and_get_started_going_to_pricing(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-nav-login', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('registration.pricing'), '#').'"[^>]*data-nav-cta>\s*Get Started#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('contact.show'), '#').'"[^>]*data-nav-expert>#', $html);
        $this->assertStringNotContainsString('>Dashboard', $html);
    }

    public function test_signed_in_customers_see_dashboard_and_no_login_link(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/features')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-nav-login', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('dashboard'), '#').'"[^>]*data-nav-cta>\s*Dashboard#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('support.index'), '#').'"[^>]*data-nav-expert>#', $html);
    }

    public function test_the_tools_menu_lists_every_published_feature_page_with_an_all_features_link(): void
    {
        FeaturePage::where('slug', 'swipe-files')->update(['is_published' => false]);
        FeaturePage::flushCache();

        $response = $this->get('/help')->assertOk();

        $response->assertSee('id="nav-panel-tools"', false)
            ->assertSee(route('features.show', 'offer-research'), false)
            ->assertSee('A full buyer brief on any product in minutes.')
            ->assertSee('Research &amp; prospecting', false)
            ->assertSee('href="'.route('features.index').'"', false)
            ->assertDontSee(route('features.show', 'swipe-files'), false);
    }

    public function test_the_resources_menu_has_faq_help_and_only_live_optional_links(): void
    {
        SiteSetting::setFlag('affiliate_program_enabled', false);

        $html = $this->get('/')->getContent();
        $resources = $this->panel($html, 'nav-panel-resources');

        $this->assertStringContainsString(route('faq.index'), $resources);
        $this->assertStringContainsString(route('help.index'), $resources);
        $this->assertStringNotContainsString(route('affiliate.landing'), $resources);
        $this->assertStringNotContainsString('>Blog', $resources);

        SiteSetting::setFlag('affiliate_program_enabled', true);
        SiteSetting::set('blog_feed_url', 'https://blog.example.com');

        $resources = $this->panel($this->get('/')->getContent(), 'nav-panel-resources');
        $this->assertStringContainsString(route('affiliate.landing'), $resources);
        $this->assertStringContainsString('https://blog.example.com', $resources);
    }

    public function test_the_mobile_drawer_pins_the_account_buttons_and_has_accordions(): void
    {
        $this->get('/')
            ->assertSee('data-mobile-drawer', false)
            ->assertSee('aria-controls="mobile-tools"', false)
            ->assertSee('aria-controls="mobile-resources"', false);
    }

    public function test_admins_can_rename_menu_items_and_reorder_resources(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(NavigationMenuEditor::class)
            ->assertSet('data.nav_tools_label', 'Tools')
            ->fillForm([
                'nav_tools_label' => 'Our tools',
                'nav_get_started_label' => 'Start now',
                'nav_resources' => [
                    ['key' => 'help', 'label' => 'Support hub', 'description' => 'Get help fast.', 'visible' => true],
                    ['key' => 'faq', 'label' => 'FAQ', 'description' => '', 'visible' => false],
                    ['key' => 'custom', 'label' => 'Community', 'description' => 'Join us.', 'url' => 'https://community.example.com', 'visible' => true],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', SiteSetting::get('nav_pricing_label'));
        auth()->logout();

        $html = $this->get('/')->getContent();
        $resources = $this->panel($html, 'nav-panel-resources');

        $this->assertStringContainsString('Our tools', $html);
        $this->assertStringContainsString('Start now', $html);
        $this->assertStringContainsString('Support hub', $resources);
        $this->assertStringContainsString('https://community.example.com', $resources);
        $this->assertStringNotContainsString(route('faq.index'), $resources);
        $this->assertLessThan(strpos($resources, 'Community'), strpos($resources, 'Support hub'));
    }

    protected function panel(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start, "Panel {$id} missing");

        return substr($html, $start, strpos($html, '</ul>', $start) - $start);
    }
}
