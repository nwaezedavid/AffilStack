<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Credits\CreditManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(CreditManager $credits): View|RedirectResponse
    {
        $user = auth()->user();

        // A team seat on an isolated plan (agency model, item 10) has no
        // offers of its own and no account-wide stats to show — its one
        // assigned offer's page is its dashboard. A shared-plan (Business
        // tier) seat has no single "home" offer — its home is the team's
        // whole offer list instead.
        if ($user->isSeat()) {
            return $user->seat_offer_id
                ? redirect()->route('offers.show', $user->seat_offer_id)
                : redirect()->route('offers.index');
        }

        $offers = $user->visibleOffers()->latest()->limit(5)->get();
        $recentGenerations = $user->visibleGenerations()->latest()->limit(8)->get();
        $balance = $credits->balance($user);
        $subscription = $user->activeSubscription()->with('plan')->first();
        $contactCount = $user->crmContacts()->count();

        return view('dashboard.index', compact(
            'offers', 'recentGenerations', 'balance', 'subscription', 'contactCount'
        ));
    }
}
