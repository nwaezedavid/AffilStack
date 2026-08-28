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

        // A team seat (item 10) has no offers of its own and no account-wide
        // stats to show — its one assigned offer's page is its dashboard.
        if ($user->isSeat()) {
            return redirect()->route('offers.show', $user->seat_offer_id);
        }

        $offers = $user->offers()->latest()->limit(5)->get();
        $recentGenerations = $user->generations()->latest()->limit(8)->get();
        $balance = $credits->balance($user);
        $subscription = $user->activeSubscription()->with('plan')->first();
        $contactCount = $user->crmContacts()->count();

        return view('dashboard.index', compact(
            'offers', 'recentGenerations', 'balance', 'subscription', 'contactCount'
        ));
    }
}
