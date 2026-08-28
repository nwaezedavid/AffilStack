<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Credits\CreditManager;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(CreditManager $credits): View
    {
        $user = auth()->user();

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
