<?php

namespace Tests\Feature;

use App\Services\Referrals\MarketingKit\BannerDesigner;
use App\Services\Referrals\MarketingKit\BannerFonts;
use App\Services\Referrals\MarketingKit\PngBannerRenderer;
use App\Services\Referrals\MarketingKit\SvgBannerRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The built-in banner set: every design/size renders as valid, self-
 * contained SVG whose text fits inside the banner, and as a real PNG.
 */
class AffiliateBannerImageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/affiliate-banners'));

        parent::tearDown();
    }

    public function test_every_design_and_size_is_valid_svg_with_text_inside_the_canvas(): void
    {
        $designer = app(BannerDesigner::class);
        $renderer = app(SvgBannerRenderer::class);
        $fonts = app(BannerFonts::class);

        foreach (array_keys(config('affiliate_kit.designs')) as $design) {
            foreach (config('affiliate_kit.sizes') as $sizeKey => $size) {
                $scene = $designer->build($design, $sizeKey);
                $svg = $renderer->render($scene);

                $xml = simplexml_load_string($svg);
                $this->assertNotFalse($xml, "{$design}/{$sizeKey} is not well-formed XML");
                $this->assertSame((string) $size['width'], (string) $xml['width']);
                $this->assertSame((string) $size['height'], (string) $xml['height']);
                $this->assertStringNotContainsString('<script', $svg);
                $this->assertStringNotContainsString('href=', $svg, 'SVG must not reference external resources');

                foreach ($scene->texts() as $text) {
                    $right = $text['x'] + $fonts->width($text['text'], $text['weight'], $text['size']);
                    $this->assertGreaterThanOrEqual(0, $text['x'], "{$design}/{$sizeKey}: '{$text['text']}' starts off-canvas");
                    $this->assertLessThanOrEqual($size['width'] + 0.5, $right, "{$design}/{$sizeKey}: '{$text['text']}' overflows the right edge");
                    $this->assertLessThanOrEqual($size['height'], $text['y'], "{$design}/{$sizeKey}: '{$text['text']}' overflows the bottom");
                }
            }
        }
    }

    public function test_svg_route_is_long_cacheable_with_an_etag(): void
    {
        $response = $this->get('/banners/all-in-one/728x90.svg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('max-age=2592000', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('@font-face', $response->getContent());

        $etag = $response->headers->get('ETag');
        $this->get('/banners/all-in-one/728x90.svg', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_png_route_renders_a_real_png_at_double_density(): void
    {
        $this->assertTrue(PngBannerRenderer::available(), 'GD with FreeType and the bundled Onest font must be available');

        $response = $this->get('/banners/commissions/300x250.png?download=1');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition', 'attachment; filename="affilstack-commissions-300x250.png"');

        $info = getimagesizefromstring($response->getContent());
        $this->assertSame([600, 500], [$info[0], $info[1]]);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_social_sizes_render_as_png_at_native_size(): void
    {
        $info = getimagesizefromstring($this->get('/banners/workflow/1200x628.png')->getContent());

        $this->assertSame([1200, 628], [$info[0], $info[1]]);
    }

    public function test_brand_assets_render_in_both_formats(): void
    {
        foreach (['icon', 'wordmark-light', 'wordmark-dark'] as $asset) {
            $this->assertNotFalse(simplexml_load_string($this->get("/brand-assets/{$asset}.svg")->assertOk()->getContent()));
            $png = getimagesizefromstring($this->get("/brand-assets/{$asset}.png")->assertOk()->getContent());
            $this->assertSame('image/png', $png['mime']);
        }

        $this->get('/brand-assets/evil.svg')->assertNotFound();
    }
}
