<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\CompetitorAngleService;
use Illuminate\Http\RedirectResponse;

class CompetitorAngleController extends Controller
{
    public function scan(Offer $offer, CompetitorAngleService $service): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        try {
            $service->queue(auth()->user(), $offer);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for a competitor angle scan. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "Competitor angle scan queued — we'll notify you the moment it's ready.");
    }
}
