<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ReferralController extends Controller
{
    /**
     * @var array<int, string>
     */
    public const TABS = ['overview', 'banners', 'emails', 'social', 'links', 'guidelines', 'payouts'];

    /**
     * The affiliate dashboard: earnings/payouts plus the Marketing Kit
     * (banners, email swipes, social posts, text links & brand assets,
     * guidelines). Every tab is rendered up front and switched client-side;
     * ?tab= deep-links to one and works without JavaScript.
     */
    public function index(Request $request, MarketingKit $kit, BannerCatalog $catalog): View
    {
        $user = auth()->user();

        $referrals = $user->referrals()
            ->with(['referredUser', 'events'])
            ->latest()
            ->get();

        $clickCount = $user->referralClicks()->count();

        $earningsByStatus = $referrals
            ->flatMap(fn ($referral) => $referral->events)
            ->groupBy('status')
            ->map(fn ($events) => $events->sum('amount_cents'));

        $totals = [
            'pending_cents' => $earningsByStatus->get('pending', 0),
            'approved_cents' => $earningsByStatus->get('approved', 0),
            'paid_cents' => $earningsByStatus->get('paid', 0),
        ];

        $payouts = $user->referralPayouts()->latest('requested_at')->get();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $stats = $kit->stats($user);
        $banners = $catalog->all()->map(fn (array $banner): array => $banner + [
            'snippets' => $kit->snippets($user, $banner),
            'link' => $kit->bannerLink($user, $banner['key']),
            'stats' => $stats['banners'][$banner['key']] ?? ['impressions' => 0, 'clicks' => 0, 'ctr' => null],
        ]);
        $swipes = $kit->swipesFor($user);
        $brand = [
            'site_name' => SiteSetting::get('site_name', 'AffilStack'),
            'logo_rectangular' => SiteSetting::get('logo_rectangular_path'),
            'logo_square' => SiteSetting::get('logo_square_path'),
            'logo_rectangular_white' => SiteSetting::get('logo_rectangular_white_path'),
            'logo_square_white' => SiteSetting::get('logo_square_white_path'),
            'colors' => [
                ['name' => 'Navy', 'hex' => strtoupper((string) SiteSetting::get('color_navy', '#0B1E3D'))],
                ['name' => 'Blue', 'hex' => strtoupper((string) SiteSetting::get('color_primary', '#2452D9'))],
                ['name' => 'Gold', 'hex' => strtoupper((string) SiteSetting::get('color_gold', '#C9A24A'))],
                ['name' => 'White', 'hex' => '#FFFFFF'],
            ],
        ];
        $links = [
            'plain' => $user->referral_link,
            'email' => $kit->trackedLink($user, 'email', 'signature'),
            'bio' => $kit->trackedLink($user, 'social', 'bio'),
        ];

        return view('dashboard.referrals.index', compact('user', 'referrals', 'clickCount', 'totals', 'payouts', 'tab', 'stats', 'banners', 'swipes', 'brand', 'links'));
    }

    /**
     * Saves (or replaces) the affiliate's payout profile. The fields
     * required depend on the chosen method — validated here rather than a
     * Form Request since the shape of "details" is entirely method-driven.
     */
    public function savePayoutMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payout_method' => ['required', 'string', 'in:'.implode(',', array_keys(config('referrals.payout_methods')))],
            'paypal_email' => ['required_if:payout_method,paypal', 'nullable', 'email'],
            'bank_account_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_account_number' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_swift_or_routing' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            // Optional — only needed for the payout wallet's automatic
            // disbursement (audit item #5). Without it a bank_transfer
            // payout still works exactly as before: an admin marks it paid
            // by hand after sending it manually. See
            // PayoutDisbursementService::canAutoDisburse().
            'bank_code' => ['nullable', 'string', 'max:20'],
        ]);

        $details = $data['payout_method'] === 'paypal'
            ? ['paypal_email' => $data['paypal_email']]
            : [
                'account_name' => $data['bank_account_name'],
                'account_number' => $data['bank_account_number'],
                'bank_name' => $data['bank_name'],
                'swift_or_routing' => $data['bank_swift_or_routing'],
                'bank_code' => $data['bank_code'] ?? null,
            ];

        $request->user()->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $details,
        ]);

        return back()->with('success', 'Payout details saved.');
    }

    public function requestPayout(Request $request, ReferralPayoutService $service): RedirectResponse
    {
        $currency = $request->string('currency')->upper()->value() ?: null;

        try {
            $service->requestPayout($request->user(), $currency);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested — you\'ll be notified once it\'s processed.');
    }
}
