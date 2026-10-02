<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One CRM list's page (members, the campaigns sent to it, "Create
 * Campaign"), plus creating, renaming/recolouring and deleting custom
 * lists. A product's own list can be renamed but not deleted while the
 * product exists — it's where that product's contacts are filed.
 */
class CrmListController extends Controller
{
    public function show(Request $request, CrmList $crmList): View
    {
        $this->authorizeList($crmList);

        $query = $crmList->contacts()->with('lists');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $members = $query->orderByPivot('added_at', 'desc')->paginate(25)->withQueryString();

        $campaigns = $crmList->campaigns()
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'replied']),
                'recipients as replied_count' => fn ($q) => $q->where('status', 'replied'),
            ])
            ->latest()
            ->get();

        $campaigns->each(fn ($campaign) => $campaign->setAttribute('new_contacts_count', $campaign->isLaunched() ? $campaign->newContactsQuery()->count() : 0));

        return view('dashboard.crm.lists.show', [
            'list' => $crmList,
            'members' => $members,
            'campaigns' => $campaigns,
            'memberCount' => $crmList->contacts()->count(),
            'withEmailCount' => $crmList->contacts()->whereNotNull('email')->where('email', '!=', '')->count(),
            'repliedCount' => $crmList->contacts()->whereNotNull('replied_at')->count(),
            'listColors' => config('crm.list_colors'),
            'lists' => auth()->user()->crmLists()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('crm_lists', 'name')->where('user_id', auth()->id())],
            'color' => ['nullable', Rule::in(config('crm.list_colors'))],
            'description' => 'nullable|string|max:500',
        ], ['name.unique' => 'You already have a list with that name.']);

        $list = auth()->user()->crmLists()->create($validated + ['color' => 'brand']);

        return redirect()->route('crm.lists.show', $list)->with('success', "List \"{$list->name}\" created — add contacts to it, then create a campaign.");
    }

    public function update(Request $request, CrmList $crmList): RedirectResponse
    {
        $this->authorizeList($crmList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('crm_lists', 'name')->where('user_id', auth()->id())->ignore($crmList->id)],
            'color' => ['required', Rule::in(config('crm.list_colors'))],
            'description' => 'nullable|string|max:500',
        ]);

        $crmList->update($validated);

        return back()->with('success', 'List updated.');
    }

    public function destroy(CrmList $crmList): RedirectResponse
    {
        $this->authorizeList($crmList);

        if ($crmList->offer_id && $crmList->offer) {
            return back()->with('error', 'This is the product\'s own list — it can\'t be deleted while the product exists. Remove contacts from it instead.');
        }

        $name = $crmList->name;
        $crmList->delete();

        return redirect()->route('crm.index')->with('success', "List \"{$name}\" deleted. Its contacts are still in your CRM.");
    }

    protected function authorizeList(CrmList $list): void
    {
        abort_unless($list->user_id === auth()->id(), 403);
    }
}
