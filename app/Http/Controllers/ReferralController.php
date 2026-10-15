<?php

namespace App\Http\Controllers;

use App\Models\ReferralCampaign;
use App\Models\User;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Services\Referrals\ReferralService;
use App\Support\SiteDomains;
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
        $campaignSlug = $request->string('c')->value();
        $campaign = $campaignSlug !== '' && strlen($campaignSlug) <= 60
            ? ReferralCampaign::where('user_id', $referrer->id)->where('slug', $campaignSlug)->first()
            : null;

        $service->recordClick(
            $referrer,
            $request,
            $bannerKey !== '' && strlen($bannerKey) <= 80 && $catalog->isValidKey($bannerKey) ? $bannerKey : null,
            in_array($source, MarketingKit::SOURCES, true) ? $source : null,
            $campaign,
        );

        $utm = array_filter($request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']), fn ($value) => is_string($value) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value) === 1);

        if ($campaign && ! isset($utm['utm_campaign'])) {
            $utm['utm_campaign'] = $campaign->slug;
        }

        $landing = static::safeLandingPath($campaign?->landing_path ?? $request->string('to')->value());
        $destination = $landing === null
            ? route('registration.pricing', $utm)
            : SiteDomains::publicUrl($landing).($utm ? '?'.http_build_query($utm) : '');

        return redirect()
            ->to($destination)
            ->withCookie($service->referralCookie($referrer))
            ->withCookie($service->campaignCookie($campaign));
    }

    /**
     * A path on the public website a referral link may land on ("/",
     * "/features/offer-research"), or null for the default pricing page.
     * Only plain site paths are accepted, so a link can never be turned
     * into an open redirect.
     */
    public static function safeLandingPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || $path === '/pricing' || ! preg_match('#^/(?:[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)?$#', $path)) {
            return null;
        }

        return $path;
    }
}
