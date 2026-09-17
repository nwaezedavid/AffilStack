<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Social\LinkedInReplyAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Task #3: "paste their reply, get an AI-drafted response" — see
 * LinkedInReplyAssistantService for the actual drafting logic.
 */
class LinkedInReplyAssistantController extends Controller
{
    public function index(Offer $offer): View
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $drafts = auth()->user()->linkedinReplyDrafts()
            ->where('offer_id', $offer->id)
            ->latest()
            ->take(10)
            ->get();

        return view('dashboard.offers.linkedin-reply-assistant', compact('offer', 'drafts'));
    }

    public function store(Request $request, Offer $offer, LinkedInReplyAssistantService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $validated = $request->validate([
            'their_message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $service->draft(auth()->user(), $offer, $validated['their_message']);
        } catch (InsufficientCreditsException $e) {
            return back()->withInput()->with('error', 'Not enough credits to draft a reply. Upgrade your plan or buy a credit top-up.');
        } catch (AIGenerationException $e) {
            return back()->withInput()->with('error', "Couldn't draft a reply right now: {$e->getMessage()}");
        }

        return redirect()->route('offers.linkedin.reply-assistant', $offer)->with('success', 'Draft ready below — copy and send it as your own.');
    }
}
