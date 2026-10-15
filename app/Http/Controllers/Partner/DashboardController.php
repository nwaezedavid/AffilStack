<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use App\Services\Referrals\PartnerStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner Portal overview: headline numbers and charts for a chosen
 * period, conversion rates, top campaigns and channels, balances and the
 * latest news.
 */
class DashboardController extends Controller
{
    public function index(Request $request, PartnerStatsService $stats): View
    {
        $user = $request->user();
        $range = PartnerStatsService::normalizeRange($request->query('range'));

        return view('partner.dashboard', [
            'user' => $user,
            'report' => $stats->report($user, $range),
            'balances' => $stats->balances($user),
            'ranges' => PartnerStatsService::RANGES,
            'news' => PartnerAnnouncement::query()->published()->latestFirst()->limit(2)->get(),
        ]);
    }
}
