<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\Generation;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Crm\CrmEmailService;
use App\Services\Modules\EmailNurtureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

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

    /**
     * CRM/email dashboard: actually send one step of an already-generated
     * sequence to its contact, via CrmEmailService.
     */
    public function send(Request $request, Generation $generation, CrmEmailService $service): RedirectResponse
    {
        abort_unless($generation->isAccessibleBy(auth()->user()), 403);

        $validated = $request->validate([
            'step' => 'required|integer|between:1,5',
        ]);

        try {
            $send = $service->sendSequenceStep($generation, (int) $validated['step']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($send->status === 'failed') {
            return back()->with('error', "Couldn't send that email: {$send->error_message}");
        }

        return back()->with('success', 'Email sent.');
    }
}
