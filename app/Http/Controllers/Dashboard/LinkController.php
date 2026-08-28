<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TrackedLink;
use Illuminate\View\View;

class LinkController extends Controller
{
    public function index(): View
    {
        $links = auth()->user()->trackedLinks()
            ->with('offer')
            ->latest('updated_at')
            ->get();

        return view('dashboard.links.index', compact('links'));
    }

    public function show(TrackedLink $trackedLink): View
    {
        abort_unless($trackedLink->user_id === auth()->id(), 403);

        $clicks = $trackedLink->clicks()->latest('clicked_at')->paginate(25);

        $byDevice = $trackedLink->clicks()
            ->selectRaw('device_type, count(*) as total')
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->pluck('total', 'device_type');

        $byCountry = $trackedLink->clicks()
            ->whereNotNull('country')
            ->selectRaw('country, count(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'country');

        $byReferrer = $trackedLink->clicks()
            ->whereNotNull('referrer')
            ->selectRaw('referrer, count(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'referrer');

        return view('dashboard.links.show', compact('trackedLink', 'clicks', 'byDevice', 'byCountry', 'byReferrer'));
    }
}
