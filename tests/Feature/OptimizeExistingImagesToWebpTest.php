<?php

namespace Tests\Feature;

use App\Models\BrandLogo;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The one-off "convert everything already uploaded" half of image
 * optimization (see WebpImageConverterTest for the conversion logic
 * itself, and the WebpFileUpload-driven tests for the going-forward
 * half). Covers every one of the six places an admin can upload an image.
 */
class OptimizeExistingImagesToWebpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_every_model_backed_image_and_updates_the_stored_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('seo/page-share.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('testimonials/avatar.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('brand-logos/acme.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('homepage-features/feature.jpg', $this->fakeJpeg());

        $page = SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'x', 'is_published' => true, 'og_image_path' => 'seo/page-share.jpg']);
        $testimonial = Testimonial::factory()->create(['avatar_path' => 'testimonials/avatar.jpg']);
        $logo = BrandLogo::factory()->create(['logo_path' => 'brand-logos/acme.jpg']);
        $feature = HomepageFeature::create(['title' => 'Fast', 'description' => 'x', 'media_type' => 'image', 'image_path' => 'homepage-features/feature.jpg', 'is_active' => true]);

        $this->artisan('images:optimize-to-webp')->assertSuccessful();

        $this->assertSame('seo/page-share.webp', $page->refresh()->og_image_path);
        $this->assertSame('testimonials/avatar.webp', $testimonial->refresh()->avatar_path);
        $this->assertSame('brand-logos/acme.webp', $logo->refresh()->logo_path);
        $this->assertSame('homepage-features/feature.webp', $feature->refresh()->image_path);

        // Originals stay put — nothing is deleted.
        Storage::disk('public')->assertExists('seo/page-share.jpg');
        Storage::disk('public')->assertExists('seo/page-share.webp');
    }

    public function test_it_converts_every_site_setting_backed_image_and_updates_the_stored_value(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/wide-logo.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('branding/square-logo.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('branding/hero.jpg', $this->fakeJpeg());
        Storage::disk('public')->put('seo/default-share.jpg', $this->fakeJpeg());

        SiteSetting::set('logo_rectangular_path', 'branding/wide-logo.jpg');
        SiteSetting::set('logo_square_path', 'branding/square-logo.jpg');
        SiteSetting::set('hero_image_path', 'branding/hero.jpg');
        SiteSetting::set('seo_og_image_path', 'seo/default-share.jpg');

        $this->artisan('images:optimize-to-webp')->assertSuccessful();

        $this->assertSame('branding/wide-logo.webp', SiteSetting::get('logo_rectangular_path'));
        $this->assertSame('branding/square-logo.webp', SiteSetting::get('logo_square_path'));
        $this->assertSame('branding/hero.webp', SiteSetting::get('hero_image_path'));
        $this->assertSame('seo/default-share.webp', SiteSetting::get('seo_og_image_path'));
    }

    public function test_a_page_with_no_image_is_skipped_without_error(): void
    {
        SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'x', 'is_published' => true]);

        $this->artisan('images:optimize-to-webp')->assertSuccessful();
    }

    public function test_running_it_twice_does_not_re_convert_already_converted_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brand-logos/acme.jpg', $this->fakeJpeg());
        $logo = BrandLogo::factory()->create(['logo_path' => 'brand-logos/acme.jpg']);

        $this->artisan('images:optimize-to-webp')->assertSuccessful();
        $pathAfterFirstRun = $logo->refresh()->logo_path;

        $this->artisan('images:optimize-to-webp')->assertSuccessful();

        $this->assertSame('brand-logos/acme.webp', $pathAfterFirstRun);
        $this->assertSame($pathAfterFirstRun, $logo->refresh()->logo_path);
    }

    protected function fakeJpeg(): string
    {
        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 200));
        ob_start();
        imagejpeg($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
