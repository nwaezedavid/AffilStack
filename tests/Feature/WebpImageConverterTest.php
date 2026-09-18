<?php

namespace Tests\Feature;

use App\Services\Media\WebpImageConverter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The core logic behind image-optimization: every already-uploaded image
 * (OptimizeExistingImagesToWebp) and every future upload (WebpFileUpload)
 * is converted to WebP through this one service. See its class docblock
 * for exactly which files it deliberately leaves untouched and why.
 */
class WebpImageConverterTest extends TestCase
{
    public function test_a_jpeg_is_converted_to_webp_and_the_original_is_kept(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.jpg', $this->fakeJpeg());

        $result = app(WebpImageConverter::class)->convert('public', 'branding/logo.jpg');

        $this->assertSame('branding/logo.webp', $result);
        Storage::disk('public')->assertExists('branding/logo.jpg');
        Storage::disk('public')->assertExists('branding/logo.webp');
        $this->assertNotNull(@imagecreatefromstring(Storage::disk('public')->get('branding/logo.webp')));
    }

    public function test_a_png_with_transparency_is_converted_to_webp_preserving_alpha(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/icon.png', $this->fakeTransparentPng());

        $result = app(WebpImageConverter::class)->convert('public', 'branding/icon.png');

        $this->assertSame('branding/icon.webp', $result);

        $decoded = imagecreatefromstring(Storage::disk('public')->get('branding/icon.webp'));
        $this->assertNotFalse($decoded);

        // The source PNG's top-left pixel is fully transparent (alpha 127
        // in GD's 0-127 scale) — that has to survive the round trip or the
        // conversion has silently flattened transparency onto a solid
        // background, which would visibly break any logo on a dark page.
        $color = imagecolorat($decoded, 0, 0);
        $alpha = ($color >> 24) & 0x7F;
        $this->assertSame(127, $alpha);
    }

    public function test_an_svg_is_left_untouched(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brand-logos/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $result = app(WebpImageConverter::class)->convert('public', 'brand-logos/mark.svg');

        $this->assertSame('brand-logos/mark.svg', $result);
    }

    public function test_an_already_webp_image_is_left_untouched(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('seo/share.webp', $this->fakeWebp());

        $result = app(WebpImageConverter::class)->convert('public', 'seo/share.webp');

        $this->assertSame('seo/share.webp', $result);
    }

    public function test_an_animated_gif_is_left_untouched(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('homepage-features/loader.gif', $this->fakeAnimatedGif());

        $result = app(WebpImageConverter::class)->convert('public', 'homepage-features/loader.gif');

        $this->assertSame('homepage-features/loader.gif', $result);
    }

    public function test_a_static_gif_is_converted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('testimonials/avatar.gif', $this->fakeStaticGif());

        $result = app(WebpImageConverter::class)->convert('public', 'testimonials/avatar.gif');

        $this->assertSame('testimonials/avatar.webp', $result);
        Storage::disk('public')->assertExists('testimonials/avatar.gif');
    }

    public function test_a_file_gd_cannot_decode_is_left_untouched(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/broken.png', 'not actually an image');

        $result = app(WebpImageConverter::class)->convert('public', 'branding/broken.png');

        $this->assertSame('branding/broken.png', $result);
    }

    public function test_a_missing_path_is_returned_unchanged(): void
    {
        Storage::fake('public');

        $result = app(WebpImageConverter::class)->convert('public', 'branding/does-not-exist.png');

        $this->assertSame('branding/does-not-exist.png', $result);
    }

    public function test_converting_the_same_image_twice_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.jpg', $this->fakeJpeg());

        $converter = app(WebpImageConverter::class);
        $first = $converter->convert('public', 'branding/logo.jpg');
        $second = $converter->convert('public', $first);

        $this->assertSame('branding/logo.webp', $first);
        $this->assertSame($first, $second);
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

    protected function fakeTransparentPng(): string
    {
        $image = imagecreatetruecolor(20, 20);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    protected function fakeWebp(): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 10, 10));
        ob_start();
        imagewebp($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    protected function fakeStaticGif(): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        ob_start();
        imagegif($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /**
     * A minimal two-frame animated GIF built by hand (GD itself can only
     * ever write a single frame), so the checker sees more than one
     * graphic-control-extension block ahead of the trailer.
     */
    protected function fakeAnimatedGif(): string
    {
        $frame = "\x00\x21\xF9\x04\x00\x00\x00\x00\x00";

        return 'GIF89a'.$frame.$frame."\x3B";
    }
}
