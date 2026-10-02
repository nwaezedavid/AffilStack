<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Filament\Pages\SiteIconSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Seo\FaviconGenerator;
use App\Support\Seo\IcoEncoder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "The favicon I uploaded doesn't show next to AffilStack in Google." The
 * root causes were an empty public/favicon.ico (served by the web server
 * ahead of anything the app did) and the uploaded icon being converted to
 * WebP, which Google's favicon crawler doesn't accept. These tests cover
 * the replacement: a real favicon set generated with GD from the original
 * upload and served from stable root URLs.
 */
class FaviconGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    protected function pngSize(string $png): array
    {
        $size = getimagesizefromstring($png);

        return [$size[0], $size[1], $size['mime']];
    }

    public function test_it_generates_a_real_ico_and_every_png_size_from_the_uploaded_icon(): void
    {
        Storage::disk('public')->put('branding/icon.png', UploadedFile::fake()->image('icon.png', 600, 400)->getContent());
        SiteSetting::set('logo_square_path', 'branding/icon.png');

        $result = app(FaviconGenerator::class)->generate();

        $this->assertSame('upload', $result['source']);
        $this->assertNotEmpty($result['warnings'], 'A non-square upload should be flagged.');

        $disk = Storage::disk('local');
        $ico = $disk->get('favicons/favicon.ico');

        // ICONDIR: reserved 0, type 1 (icon), 3 images.
        $this->assertSame("\x00\x00\x01\x00\x03\x00", substr($ico, 0, 6));
        $entries = IcoEncoder::entries($ico);
        $this->assertSame([16, 32, 48], array_column($entries, 'width'));
        $this->assertSame([16, 32, 48], array_column($entries, 'height'));

        foreach ($entries as $entry) {
            $png = substr($ico, $entry['offset'], $entry['bytes']);
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
            $this->assertSame([$entry['width'], $entry['width'], 'image/png'], $this->pngSize($png));
        }

        foreach ([48, 96, 192, 512] as $size) {
            $this->assertSame([$size, $size, 'image/png'], $this->pngSize($disk->get("favicons/favicon-{$size}x{$size}.png")));
        }

        $this->assertSame([180, 180, 'image/png'], $this->pngSize($disk->get('favicons/apple-touch-icon.png')));
    }

    public function test_the_web_manifest_names_the_site_and_lists_its_icons(): void
    {
        SiteSetting::set('site_name', 'AffilStack');
        SiteSetting::set('color_navy', '#0B1E3D');

        app(FaviconGenerator::class)->generate();

        $manifest = json_decode(Storage::disk('local')->get('favicons/site.webmanifest'), true);

        $this->assertSame('AffilStack', $manifest['name']);
        $this->assertSame('AffilStack', $manifest['short_name']);
        $this->assertSame('#0B1E3D', $manifest['theme_color']);
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));
        $this->assertStringStartsWith('/favicon-192x192.png?v=', $manifest['icons'][0]['src']);
    }

    public function test_without_an_upload_a_default_monogram_icon_is_generated(): void
    {
        $result = app(FaviconGenerator::class)->generate();

        $this->assertSame('default', $result['source']);
        $this->assertSame([512, 512, 'image/png'], $this->pngSize(Storage::disk('local')->get('favicons/favicon-512x512.png')));
    }

    public function test_the_original_png_is_used_when_the_stored_icon_path_points_at_a_webp_copy(): void
    {
        Storage::disk('public')->put('branding/icon.png', UploadedFile::fake()->image('icon.png', 512, 512)->getContent());
        Storage::disk('public')->put('branding/icon.webp', 'not-a-real-image');
        SiteSetting::set('logo_square_path', 'branding/icon.webp');

        $result = app(FaviconGenerator::class)->generate();

        $this->assertSame('upload', $result['source']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_favicon_files_are_served_from_stable_root_urls_with_caching_and_no_cookies(): void
    {
        $files = [
            '/favicon.ico' => 'image/x-icon',
            '/favicon-48x48.png' => 'image/png',
            '/favicon-96x96.png' => 'image/png',
            '/favicon-192x192.png' => 'image/png',
            '/favicon-512x512.png' => 'image/png',
            '/apple-touch-icon.png' => 'image/png',
            '/site.webmanifest' => 'application/manifest+json',
        ];

        foreach ($files as $path => $type) {
            $response = $this->get($path.'?v=abc');

            $response->assertOk();
            $response->assertHeader('Content-Type', $type);
            $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
            $this->assertEmpty($response->headers->getCookies(), "{$path} must not set cookies");
        }

        $bare = $this->get('/favicon.ico');
        $this->assertStringContainsString('max-age=86400', (string) $bare->headers->get('Cache-Control'));
        $this->assertSame("\x00\x00\x01\x00", substr($bare->getContent(), 0, 4));

        $this->get('/favicon.ico', ['If-None-Match' => $bare->headers->get('ETag')])->assertStatus(304);
        $this->get('/favicon-64x64.png')->assertNotFound();
    }

    public function test_the_set_rebuilds_itself_when_the_icon_changes_outside_the_admin_form(): void
    {
        $first = $this->get('/favicon-48x48.png')->getContent();

        Storage::disk('public')->put('branding/new.png', UploadedFile::fake()->image('new.png', 512, 512)->getContent());
        SiteSetting::set('logo_square_path', 'branding/new.png');

        $this->assertFalse(app(FaviconGenerator::class)->isFresh());
        $second = $this->get('/favicon-48x48.png')->getContent();

        $this->assertNotSame($first, $second);
        $this->assertTrue(app(FaviconGenerator::class)->isFresh());
        $this->assertSame('upload', app(FaviconGenerator::class)->meta()['source']);
    }

    public function test_the_static_empty_favicon_that_shadowed_the_real_one_is_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('favicon.ico'));
    }

    public function test_the_artisan_command_rebuilds_the_set_and_removes_an_empty_static_favicon(): void
    {
        $static = public_path('favicon.ico');
        file_put_contents($static, '');

        try {
            $this->artisan('seo:generate-favicons')->assertSuccessful();

            $this->assertFileDoesNotExist($static);
            Storage::disk('local')->assertExists('favicons/favicon.ico');
        } finally {
            @unlink($static);
        }
    }

    public function test_uploading_the_square_logo_in_brand_settings_keeps_the_original_and_builds_the_set(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandSettings::class)
            ->fillForm([
                'site_name' => 'AffilStack',
                'logo_square' => UploadedFile::fake()->image('square.png', 512, 512),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = SiteSetting::get('logo_square_path');
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('upload', app(FaviconGenerator::class)->meta()['source']);
    }

    public function test_the_site_icon_page_saves_the_icon_regenerates_and_shows_the_health_checks(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SiteIconSettings::class)
            ->assertSee('Search-engine readiness')
            ->assertSee('robots.txt lets Googlebot and Googlebot-Image crawl the homepage and the icon')
            ->fillForm(['logo_square' => UploadedFile::fake()->image('icon.png', 512, 512)])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertStringEndsWith('.png', SiteSetting::get('logo_square_path'));
        $this->assertTrue(app(FaviconGenerator::class)->isFresh());
    }

    public function test_the_dashboard_and_guest_layouts_link_the_favicon_set(): void
    {
        $this->get('/login')->assertOk()->assertSee(url('/favicon-48x48.png'), false)->assertSee('rel="manifest"', false);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee(url('/favicon.ico'), false);
    }
}
