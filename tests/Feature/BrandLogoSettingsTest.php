<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two logo variants (Brand Settings, "Identity" section): a rectangular
 * logo for the header/sign-in/sign-up pages, and a square/circle logo used
 * as the favicon, apple-touch-icon, and anywhere a square mark fits
 * better. Every render site needs to pick up whichever one is uploaded
 * without any further admin action.
 */
class BrandLogoSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_admin_can_save_both_logo_variants(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(BrandSettings::class)
            ->fillForm([
                'site_name' => 'AffilStack',
                'logo_rectangular' => UploadedFile::fake()->image('wide-logo.png'),
                'logo_square' => UploadedFile::fake()->image('square-logo.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $rectangular = SiteSetting::get('logo_rectangular_path');
        $square = SiteSetting::get('logo_square_path');

        $this->assertNotEmpty($rectangular);
        $this->assertNotEmpty($square);
        $this->assertNotSame($rectangular, $square);
        Storage::disk('public')->assertExists($rectangular);
        Storage::disk('public')->assertExists($square);
    }

    public function test_homepage_header_and_favicon_use_the_correct_variant(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/wide-logo.png');
        SiteSetting::set('logo_square_path', 'branding/square-logo.png');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('branding/wide-logo.png', false);
        $response->assertSee('branding/square-logo.png', false);
        $response->assertDontSee('logo_path', false);
    }

    public function test_login_page_uses_the_rectangular_logo(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/wide-logo.png');
        SiteSetting::set('logo_square_path', 'branding/square-logo.png');

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('branding/wide-logo.png', false);
    }

    public function test_dashboard_sidebar_uses_the_rectangular_logo(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/wide-logo.png');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('branding/wide-logo.png', false);
    }

    public function test_admin_panel_uses_rectangular_logo_and_square_favicon(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/wide-logo.png');
        SiteSetting::set('logo_square_path', 'branding/square-logo.png');

        $panel = Filament::getPanel('admin');

        $this->assertStringContainsString('branding/wide-logo.png', (string) $panel->getBrandLogo());
        $this->assertStringContainsString('branding/square-logo.png', (string) $panel->getFavicon());
    }

    public function test_apple_touch_icon_uses_the_square_logo(): void
    {
        SiteSetting::set('logo_square_path', 'branding/square-logo.png');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="apple-touch-icon"', false);
    }
}
