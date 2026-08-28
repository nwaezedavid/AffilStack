<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\XService;
use Illuminate\Http\RedirectResponse;

class XController extends Controller
{
    public function thread(Offer $offer, XService $service): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        if (! auth()->user()->canUseChannel('x')) {
            return back()->with('error', 'The X module isn\'t included in your current plan — upgrade to unlock it.');
        }

        try {
            $service->queue(auth()->user(), $offer);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for an X thread. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "X thread queued — we'll notify you the moment it's ready.");
    }
}
