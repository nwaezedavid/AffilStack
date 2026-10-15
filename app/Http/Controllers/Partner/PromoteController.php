<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Support\ReferralProgram;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ready-made email swipes and social posts (with the partner's link
 * already inside), and the promotion guidelines.
 */
class PromoteController extends Controller
{
    /**
     * The portal page that holds each Marketing Kit section, so the shared
     * kit partials can link between sections.
     */
    public static function routeForTab(string $tab): string
    {
        return match ($tab) {
            'banners' => 'partner.banners',
            'emails', 'social' => 'partner.promote',
            'links' => 'partner.links',
            'guidelines' => 'partner.guidelines',
            'payouts' => 'partner.payouts',
            default => 'partner.dashboard',
        };
    }

    public function index(Request $request, MarketingKit $kit): View
    {
        return view('partner.promote', [
            'user' => $request->user(),
            'swipes' => $kit->swipesFor($request->user()),
            'links' => ['bio' => $kit->trackedLink($request->user(), 'social', 'bio')],
            'kitTabUrl' => fn (string $tab): string => route(static::routeForTab($tab)),
        ]);
    }

    public function guidelines(Request $request): View
    {
        return view('partner.guidelines', [
            'user' => $request->user(),
            'commissionPercent' => rtrim(ReferralProgram::formatPercent(ReferralProgram::partnerPercent()), '%'),
            'cookieDays' => ReferralProgram::cookieDays(),
            'kitTabUrl' => fn (string $tab): string => route(static::routeForTab($tab)),
        ]);
    }
}
