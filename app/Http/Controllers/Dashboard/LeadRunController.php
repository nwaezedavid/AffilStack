<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LeadRun;
use App\Models\Offer;
use App\Services\Leads\LeadFinder;
use App\Services\Leads\LeadFinderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Find leads" on a product page — starts a background B2B lead search
 * (see LeadFinder). Owner-only, like the CRM the leads land in.
 */
class LeadRunController extends Controller
{
    public function store(Request $request, Offer $offer, LeadFinder $finder): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id() && ! $offer->is_sandbox, 404);

        if ($offer->status !== 'ready') {
            return back()->with('error', 'Finish the product research first — it tells AffilStack who to look for.');
        }

        $validated = $request->validate([
            'target' => ['required', 'integer', 'in:'.implode(',', (array) config('leads.run_sizes'))],
        ]);

        try {
            $finder->queue(auth()->user(), $offer, (int) $validated['target']);
        } catch (LeadFinderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AffilStack is finding leads for '.$offer->product_name.'. They\'ll appear in your CRM in a few minutes — we\'ll notify you.');
    }

    public function status(Offer $offer): JsonResponse
    {
        abort_unless($offer->user_id === auth()->id(), 404);

        $run = LeadRun::query()->where('offer_id', $offer->id)->latest('id')->first();

        return response()->json([
            'status' => $run?->status,
            'active' => (bool) $run?->isActive(),
            'saved' => $run?->saved,
            'message' => $run?->message,
        ]);
    }
}
