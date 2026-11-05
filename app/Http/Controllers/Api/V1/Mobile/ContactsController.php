<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\CrmController;
use App\Http\Resources\Mobile\MobileContactResource;
use App\Models\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * CRM contacts in the phone app: search (name, company or email, like the
 * dashboard), filter by status or tag, open one, and update its status,
 * tags and notes. Same ownership rule as /api/v1/crm-contacts.
 */
class ContactsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $tag = trim((string) $request->query('tag', ''));

        $contacts = $request->user()->crmContacts()
            ->where('is_sandbox', false)
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('company', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when(in_array($status, CrmController::STATUSES, true), fn ($query) => $query->where('status', $status))
            // Tags are a JSON list; matching the quoted value works on MySQL
            // and SQLite alike.
            ->when($tag !== '', fn ($query) => $query->where('tags', 'like', '%'.json_encode($tag).'%'))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 25), 1), 50));

        return MobileContactResource::collection($contacts);
    }

    public function show(Request $request, CrmContact $contact): MobileContactResource
    {
        abort_unless($contact->user_id === $request->user()->id && ! $contact->is_sandbox, 404);

        return new MobileContactResource($contact->load('lists:id,name'));
    }

    public function update(Request $request, CrmContact $contact): MobileContactResource
    {
        abort_unless($contact->user_id === $request->user()->id && ! $contact->is_sandbox, 404);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(CrmController::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ]);

        if (array_key_exists('tags', $validated)) {
            $validated['tags'] = array_values(array_unique(array_filter(array_map('trim', $validated['tags']))));
        }

        $contact->update($validated);

        return new MobileContactResource($contact->load('lists:id,name'));
    }
}
