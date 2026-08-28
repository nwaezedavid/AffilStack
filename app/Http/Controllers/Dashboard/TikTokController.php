<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\TikTokService;
use Illuminate\Http\RedirectResponse;

class TikTokController extends Controller
{
    public function video(Offer $offer, TikTokService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        if (! auth()->user()->canUseChannel('tiktok')) {
            return back()->with('error', 'The TikTok module isn\'t included in your current plan — upgrade to unlock it.');
        }

        try {
            $service->queue(auth()->user(), $offer);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for a TikTok video package. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "TikTok video package queued — we'll notify you the moment it's ready.");
    }
}
