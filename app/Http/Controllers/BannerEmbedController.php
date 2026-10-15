<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Support\SiteDomains;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The embeddable affiliate banner: an iframe page any site may frame
 * (/embed/banner/{size}?ref=CODE&design=…) and a one-line loader script
 * (/embed/banner.js) that injects that iframe. Runs outside the "web"
 * middleware group, so it sets no cookies; it renders nothing about the
 * affiliate beyond their public referral code. A click opens /r/{code} in a
 * new top-level tab, which is where the first-party 60-day attribution
 * cookie is set — the iframe itself never needs third-party cookies.
 */
class BannerEmbedController extends Controller
{
    public function frame(Request $request, string $size, BannerCatalog $catalog, MarketingKit $kit): Response
    {
        abort_unless($catalog->isSize($size), 404);

        $affiliate = $this->affiliate((string) $request->query('ref', ''));
        $design = (string) $request->query('design', 'rotate');
        // A signed link (the admin's preview) may show an ad that is
        // switched off or scheduled for later.
        $banner = $catalog->pick($size, preg_match('/^[a-z0-9-]{1,40}$/', $design) ? $design : 'rotate', $request->hasValidSignature());

        if ($affiliate) {
            $link = $kit->bannerLink($affiliate, $banner['key']);

            if (! $request->boolean('preview')) {
                $kit->recordImpression($affiliate, $banner['key'], $request);
            }
        } else {
            $landing = ($banner['landing_path'] ?? null) ?: '/';
            $link = SiteDomains::publicUrl($landing).'?'.http_build_query(['utm_source' => 'affiliate', 'utm_medium' => 'banner', 'utm_content' => $banner['key']]);
        }

        return response()
            ->view($banner['kind'] === 'ad' ? 'embed.banner-ad' : 'embed.banner', ['banner' => $banner, 'link' => $link])
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function script(): Response
    {
        return response()
            ->view('embed.banner-script', ['endpoint' => SiteDomains::publicUrl('/embed/banner')])
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Unknown, malformed or suspended codes still get a working banner — it
     * just links to the homepage instead of crediting anyone.
     */
    private function affiliate(string $code): ?User
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) {
            return null;
        }

        return User::query()->where('referral_code', $code)->where('is_suspended', false)->first();
    }
}
