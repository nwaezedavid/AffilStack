<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\PinterestService;
use Illuminate\Http\RedirectResponse;

class PinterestController extends Controller
{
    public function pins(Offer $offer, PinterestService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        if (! auth()->user()->canUseChannel('pinterest')) {
            return back()->with('error', 'The Pinterest module isn\'t included in your current plan — upgrade to unlock it.');
        }

        try {
            $service->queue(auth()->user(), $offer);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for Pinterest pins. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "Pinterest pins queued — we'll notify you the moment they're ready.");
    }
}
