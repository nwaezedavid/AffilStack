<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Support\SiteDomains;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Banners & ads: the auto-updating ad slot (one line of code that always
 * shows AffilStack's current ads — copy, discounts and images change on
 * the partner's site whenever the admin updates them), plus the full
 * banner gallery with embed codes and per-banner stats.
 */
class BannersController extends Controller
{
    public function index(Request $request, MarketingKit $kit, BannerCatalog $catalog): View
    {
        $user = $request->user();
        $stats = $kit->stats($user);
        $code = $user->referralCode();
        $script = SiteDomains::publicRoute('embed.banner-script');

        $banners = $catalog->all()->map(fn (array $banner): array => $banner + [
            'snippets' => $kit->snippets($user, $banner),
            'link' => $kit->bannerLink($user, $banner['key']),
            'stats' => $stats['banners'][$banner['key']] ?? ['impressions' => 0, 'clicks' => 0, 'ctr' => null],
        ]);

        $slots = collect(BannerAd::SIZES)
            ->filter(fn (string $size): bool => $catalog->isSize($size))
            ->mapWithKeys(fn (string $size): array => [$size => [
                'label' => $catalog->sizes()[$size]['label'],
                'width' => $catalog->sizes()[$size]['width'],
                'height' => $catalog->sizes()[$size]['height'],
                'script' => '<script async src="'.e($script).'" data-ref="'.e($code).'" data-size="'.$size.'"></script>',
                'preview' => route('embed.banner', ['size' => $size, 'preview' => 1]),
            ]]);

        return view('partner.banners', [
            'user' => $user,
            'banners' => $banners,
            'slots' => $slots,
            'liveAds' => BannerAd::query()->live()->count(),
            'kitTabUrl' => fn (string $tab): string => route(PromoteController::routeForTab($tab)),
        ]);
    }
}
