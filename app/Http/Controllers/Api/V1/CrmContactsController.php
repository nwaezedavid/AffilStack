<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRM contacts, via the API (task #6) — the clearest "automate an
 * activity" case in the request: a team's own external tool (a form
 * handler, another CRM, a lead-gen script) pushing new contacts into
 * AffilStack's pipeline without a human re-typing them. Deliberately not
 * team-shared like visibleOffers()/visibleGenerations() — CRM contacts
 * were never part of the Business tier's shared-offer model (see
 * User::crmContacts(), unchanged), so a seat's token sees only contacts
 * it created itself, exactly like the dashboard.
 */
class CrmContactsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contacts = $request->user()->crmContacts()
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($contacts);
    }

    public function show(Request $request, CrmContact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        return response()->json($contact);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->crmContactLimitReached()) {
            return response()->json(['message' => "You've reached your plan's CRM contact limit."], 402);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:2048',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $contact = CrmContact::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'source' => 'api',
        ]);

        return response()->json($contact, 201);
    }

    public function update(Request $request, CrmContact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:2048',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:new,contacted,qualified,customer,unqualified',
        ]);

        $contact->update($validated);

        return response()->json($contact);
    }
}
