<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRM contacts, via the API (task #6) — the clearest "automate an
 * activity" case in the request: a team's own external tool (a form
 * handler, another CRM, a lead-gen script) pushing new contacts into
 * AffilStack's pipeline without a human re-typing them. Deliberately not
 * team-shared like visibleOffers()/visibleGenerations() — CRM contacts
 * were never part of the Business tier's shared-offer model (see
 * User::crmContacts(), unchanged), so a seat's token sees only contacts
 * it created itself, exactly like the dashboard.
 *
 * API roadmap item #5 (sandbox mode): every method scopes by is_sandbox to
 * match the calling token, mirroring OffersController. Item #6 adds
 * bulkStore() alongside the original single store().
 */
class CrmContactsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        $contacts = $request->user()->crmContacts()
            ->where('is_sandbox', $isSandbox)
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($contacts);
    }

    public function show(Request $request, CrmContact $contact): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        abort_unless($contact->user_id === $request->user()->id && $contact->is_sandbox === $isSandbox, 404);

        return response()->json($contact);
    }

    public function store(Request $request): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        // A sandbox token's writes never count against (or get blocked by)
        // the account's real plan limit — see API roadmap item #5.
        if (! $isSandbox && $request->user()->crmContactLimitReached()) {
            return response()->json(['message' => "You've reached your plan's CRM contact limit."], 402);
        }

        $validated = $request->validate($this->contactValidationRules());

        $contact = CrmContact::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'source' => 'api',
            'is_sandbox' => $isSandbox,
        ]);

        return response()->json($contact, 201);
    }

    /**
     * API roadmap item #6 — accepts up to config('api_billing.bulk_max_items')
     * contacts in one call. Deliberately partial-success rather than
     * all-or-nothing: the target use case is a one-time migration/import
     * where one malformed row shouldn't block the other 99 (see the
     * roadmap doc). Every valid item is created; every invalid one is
     * reported by its position in the submitted array so the caller knows
     * exactly what to fix and retry.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $maxItems = (int) config('api_billing.bulk_max_items', 100);

        $request->validate([
            'contacts' => ['required', 'array', 'min:1', "max:{$maxItems}"],
        ]);

        $user = $request->user();
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        // Same "0 = unlimited" convention as User::crmContactLimitReached(),
        // computed once and decremented locally rather than re-querying
        // count() once per item. Never enforced for sandbox data.
        $limit = $isSandbox ? 0 : (int) ($user->activeSubscription?->plan?->contact_limit ?? 0);
        $remaining = $limit > 0 ? max(0, $limit - $user->crmContacts()->count()) : null;

        $created = [];
        $failed = [];

        foreach (array_values($request->input('contacts')) as $index => $item) {
            if ($remaining !== null && $remaining <= 0) {
                $failed[] = ['index' => $index, 'errors' => ['limit' => ["You've reached your plan's CRM contact limit."]]];

                continue;
            }

            $validator = Validator::make(is_array($item) ? $item : [], $this->contactValidationRules());

            if ($validator->fails()) {
                $failed[] = ['index' => $index, 'errors' => $validator->errors()->toArray()];

                continue;
            }

            $created[] = CrmContact::create([
                ...$validator->validated(),
                'user_id' => $user->id,
                'source' => 'api',
                'is_sandbox' => $isSandbox,
            ]);

            if ($remaining !== null) {
                $remaining--;
            }
        }

        return response()->json(['created' => $created, 'failed' => $failed]);
    }

    public function update(Request $request, CrmContact $contact): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        abort_unless($contact->user_id === $request->user()->id && $contact->is_sandbox === $isSandbox, 404);

        $validated = $request->validate([
            ...$this->contactValidationRules(),
            'status' => 'nullable|in:new,contacted,qualified,customer,unqualified',
        ]);

        $contact->update($validated);

        return response()->json($contact);
    }

    /**
     * Shared between store(), bulkStore(), and update() (plus its own
     * 'status' field) so the three can never quietly drift into accepting
     * different shapes for the same resource.
     *
     * @return array<string, string>
     */
    private function contactValidationRules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:2048',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
