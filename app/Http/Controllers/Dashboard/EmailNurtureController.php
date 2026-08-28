<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\EmailNurtureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailNurtureController extends Controller
{
    public function generate(Request $request, Offer $offer, EmailNurtureService $service): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'contact_id' => 'required|exists:crm_contacts,id',
        ]);

        $contact = CrmContact::findOrFail($validated['contact_id']);
        abort_unless($contact->user_id === auth()->id(), 403);

        try {
            $service->queue(auth()->user(), $offer, $contact);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for an email nurture sequence. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "Email sequence for {$contact->name} queued — we'll notify you the moment it's ready.");
    }
}
