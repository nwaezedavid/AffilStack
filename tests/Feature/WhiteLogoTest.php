<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Extension\ExtensionPackager;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * "I will like to upload a white version of my logo and icon that will be
 * used in dark areas" — Brand Settings takes a white wide logo and a white
 * square icon, used on the dark footer, the dashboard sidebar, the admin
 * panel in dark mode and the Chrome extension header.
 */
class WhiteLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_brand_settings_saves_the_white_logo_and_icon(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(BrandSettings::class)
            ->assertSee('White logo & icon (for dark backgrounds)')
            ->fillForm([
                'site_name' => 'AffilStack',
                'logo_rectangular_white' => UploadedFile::fake()->image('white-wide.png', 600, 150),
                'logo_square_white' => UploadedFile::fake()->image('white-icon.png', 512, 512),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertExists(SiteSetting::get('logo_rectangular_white_path'));
        Storage::disk('public')->assertExists(SiteSetting::get('logo_square_white_path'));
    }

    public function test_the_footer_uses_the_white_logo_and_the_header_keeps_the_colour_one(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/colour.webp');
        SiteSetting::set('logo_rectangular_white_path', 'branding/white.webp');

        $html = $this->get('/')->assertOk()->getContent();

        $footer = substr($html, strpos($html, 'id="site-footer-heading"'));
        $this->assertStringContainsString('branding/white.webp', $footer);
        $this->assertStringNotContainsString('branding/colour.webp', $footer);

        $header = substr($html, strpos($html, 'data-site-header'), 4000);
        $this->assertStringContainsString('branding/colour.webp', $header);
        $this->assertStringNotContainsString('branding/white.webp', $header);
    }

    public function test_without_a_white_logo_the_footer_keeps_its_monogram(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/colour.webp');

        $html = $this->get('/')->assertOk()->getContent();
        $footer = substr($html, strpos($html, 'id="site-footer-heading"'));

        $this->assertStringNotContainsString('branding/colour.webp', $footer);
        $this->assertStringContainsString('>AF</span>', $footer);
    }

    public function test_the_dashboard_sidebar_prefers_the_white_logo(): void
    {
        SiteSetting::set('logo_rectangular_path', 'branding/colour.webp');
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('branding/colour.webp');

        SiteSetting::set('logo_rectangular_white_path', 'branding/white.webp');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('branding/white.webp')
            ->assertDontSee('branding/colour.webp');
    }

    public function test_the_admin_panel_uses_the_white_logo_in_dark_mode(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getDarkModeBrandLogo());

        SiteSetting::set('logo_rectangular_white_path', 'branding/white.webp');

        $this->assertStringEndsWith('branding/white.webp', (string) Filament::getPanel('admin')->getDarkModeBrandLogo());
    }

    public function test_the_extension_header_uses_the_white_icon_without_the_white_chip(): void
    {
        $default = $this->extensionEntries();
        $this->assertSame(file_get_contents(resource_path('browser-extension/icons/header-logo.png')), $default['icons/header-logo.png']);
        $this->assertStringContainsString('class="logo" src="icons/header-logo.png"', $default['popup.html']);

        $image = imagecreatetruecolor(400, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('branding/white-icon.png', (string) ob_get_clean());
        SiteSetting::set('logo_square_white_path', 'branding/white-icon.png');

        $branded = $this->extensionEntries();
        [$width, $height] = getimagesizefromstring($branded['icons/header-logo.png']);
        $this->assertSame([64, 64], [$width, $height]);
        $this->assertNotSame($default['icons/header-logo.png'], $branded['icons/header-logo.png']);
        $this->assertStringContainsString('class="logo logo-on-dark" src="icons/header-logo.png"', $branded['popup.html']);
        $this->assertStringContainsString('class="logo logo-on-dark" src="icons/header-logo.png"', $branded['options.html']);
        // The toolbar icons stay the normal (colour) icon.
        $this->assertSame($default['icons/icon48.png'], $branded['icons/icon48.png']);
    }

    /**
     * @return array<string, string>
     */
    protected function extensionEntries(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ext').'.zip';
        app(ExtensionPackager::class)->build($path, forStore: true);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($path);

        return $entries;
    }
}
