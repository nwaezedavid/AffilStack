<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Credits\CreditManager;
use App\Support\OfferHubData;
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

        // An affiliate-only account (see RestrictAffiliateOnlyAccounts) has
        // no offers, subscription, or account-wide stats to show — its
        // referral link and earnings are its whole account.
        if ($user->isAffiliateOnly()) {
            return redirect()->route('partner.dashboard');
        }

        // Get Started: the research form first, then the results for the
        // most recent product right below it (polled live while it runs).
        $offers = $user->visibleOffers()->latest()->limit(6)->get();
        $latestOffer = $offers->first();
        $balance = $credits->balance($user);
        $subscription = $user->activeSubscription()->with('plan')->first();
        $contactCount = $user->crmContacts()->count();
        $productCount = $user->visibleOffers()->count();

        return view('dashboard.index', array_merge(
            compact('offers', 'latestOffer', 'balance', 'subscription', 'contactCount', 'productCount'),
            $latestOffer ? OfferHubData::for($user, $latestOffer) : [],
        ));
    }
}
