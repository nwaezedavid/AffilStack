<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated referral redirect — whoever clicks /r/{code} is a
 * prospective signup, not an AffilStack user yet. Logs the click, sets the
 * attribution cookie RegistrationController::store() reads later, and sends
 * them to pricing. An unrecognized code degrades to a plain, cookie-less
 * redirect to pricing rather than a 404 — a stale or mistyped referral link
 * should never look broken to the visitor. Marketing Kit links add "b" (the
 * banner key) and utm_* params; the banner/channel are logged on the click
 * and the utm_* params are passed on to pricing for analytics.
 */
class ReferralController extends Controller
{
    public function redirect(string $code, Request $request, ReferralService $service, BannerCatalog $catalog): RedirectResponse
    {
        $referrer = User::where('referral_code', $code)->first();

        if (! $referrer) {
            return redirect()->route(config('referrals.fallback_route'));
        }

        $bannerKey = $request->string('b')->value();
        $source = $request->string('utm_medium')->value();

        $service->recordClick(
            $referrer,
            $request,
            $bannerKey !== '' && strlen($bannerKey) <= 80 && $catalog->isValidKey($bannerKey) ? $bannerKey : null,
            in_array($source, MarketingKit::SOURCES, true) ? $source : null,
        );

        $utm = array_filter($request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']), fn ($value) => is_string($value) && strlen($value) <= 100);

        return redirect()
            ->route('registration.pricing', $utm)
            ->cookie($service->referralCookie($referrer));
    }
}
