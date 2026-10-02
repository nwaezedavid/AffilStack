<?php

namespace App\Http\Controllers;

use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\BannerDesigner;
use App\Services\Referrals\MarketingKit\BannerScene;
use App\Services\Referrals\MarketingKit\PngBannerRenderer;
use App\Services\Referrals\MarketingKit\SvgBannerRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, cookie-less image URLs for the built-in affiliate banners
 * (/banners/{design}/{size}.svg|png) and the AffilStack brand mark
 * (/brand-assets/{asset}.svg|png). URLs are stable — affiliates paste them
 * into other sites and emails — so responses are long-cacheable with an
 * ETag derived from exactly what was drawn.
 */
class AffiliateBannerController extends Controller
{
    public function __construct(
        private BannerDesigner $designer,
        private SvgBannerRenderer $svg,
        private PngBannerRenderer $png,
    ) {}

    public function image(Request $request, BannerCatalog $catalog, string $design, string $size, string $format): Response
    {
        abort_unless($catalog->isDesign($design) && $catalog->isSize($size), 404);

        return $this->respond($request, $this->designer->build($design, $size), $format, "affilstack-{$design}-{$size}", false);
    }

    public function brandAsset(Request $request, string $asset, string $format): Response
    {
        return $this->respond($request, $this->designer->brandMark($asset), $format, "affilstack-{$asset}", true);
    }

    private function respond(Request $request, BannerScene $scene, string $format, string $name, bool $transparent): Response
    {
        if ($format === 'png' && ! PngBannerRenderer::available()) {
            return redirect()->to(str_replace('.png', '.svg', $request->url()), 302);
        }

        $etag = '"'.$scene->fingerprint().'-'.$format.'"';
        $headers = [
            'Cache-Control' => 'public, max-age='.config('affiliate_kit.image_cache_seconds'),
            'ETag' => $etag,
            'Access-Control-Allow-Origin' => '*',
        ];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$name.'.'.$format.'"';
        }

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        $body = $format === 'svg'
            ? $this->svg->render($scene)
            : $this->png->cached($scene, $name, $transparent);

        return response($body, 200, $headers + ['Content-Type' => $format === 'svg' ? 'image/svg+xml' : 'image/png']);
    }
}
