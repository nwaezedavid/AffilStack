<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(): View
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

        return view('dashboard.referrals.index', compact('user', 'referrals', 'clickCount', 'totals'));
    }
}
