<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmList;
use App\Models\CrmReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CRM, organised around lists: every researched product gets its own
 * list (CrmList::forOffer) so its prospects never mix with another
 * product's, plus any custom lists the user makes. Campaigns are always
 * sent to a list — see CrmCampaignController.
 */
class CrmController extends Controller
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'customer', 'unqualified'];

    public const SOURCES = ['manual', 'import', 'google_maps', 'extension', 'linkedin', 'api'];

    /**
     * Public, unauthenticated — see routes/web.php and CrmEmailService.
     * Also stops any campaign follow-ups still scheduled for the contact.
     */
    public function unsubscribe(string $token): View
    {
        $contact = CrmContact::where('unsubscribe_token', $token)->firstOrFail();

        if (! $contact->isUnsubscribed()) {
            $contact->update(['unsubscribed_at' => now()]);
            $contact->campaignRecipients()->whereIn('status', ['queued', 'sent'])->update(['status' => 'unsubscribed', 'next_step_due_at' => null]);
        }

        return view('marketing.crm-unsubscribed', ['senderName' => $contact->user->name]);
    }

    /**
     * Pipeline stats + lists sidebar + the filterable contacts table.
     * `q` matches name/company/email; `status`, `source` and `list` narrow
     * further. The stats header always reflects the whole pipeline.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $this->ensureProductLists($user);

        $lists = $this->listsFor($user);
        $activeList = $request->filled('list') ? $lists->firstWhere('id', (int) $request->query('list')) : null;

        $contacts = $this->filteredContacts($request, $user, $activeList)
            ->with('lists')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.crm.index', [
            'contacts' => $contacts,
            'stats' => $this->stats($user),
            'lists' => $lists,
            'activeList' => $activeList,
            'unreadReplies' => CrmReply::where('user_id', $user->id)->whereNull('read_at')->count(),
            'recentCampaigns' => $user->crmCampaigns()->with('list')->latest()->limit(4)->get(),
            'listColors' => config('crm.list_colors'),
            'sources' => self::SOURCES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()->crmContactLimitReached()) {
            return back()->with('error', 'You\'ve reached your plan\'s CRM contact limit. Upgrade to add more.');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255|required_without_all:company,email,phone',
            'company' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:2048',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'list_id' => ['nullable', 'integer', Rule::exists('crm_lists', 'id')->where('user_id', auth()->id())],
        ], [
            'name.required_without_all' => 'Add at least a name, company, email or phone number.',
        ]);

        $listId = $validated['list_id'] ?? null;
        unset($validated['list_id']);

        $validated['user_id'] = auth()->id();
        $validated['source'] = 'manual';

        $contact = CrmContact::create($validated);

        if ($listId) {
            CrmList::find($listId)->addContact($contact);
        }

        return back()->with('success', 'Contact saved'.($listId ? ' to '.CrmList::find($listId)->name : '').'.');
    }

    /**
     * Pipeline status changes (the table's dropdown) and contact edits.
     */
    public function update(Request $request, CrmContact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'sometimes|required|in:'.implode(',', self::STATUSES),
            'name' => 'sometimes|nullable|string|max:255',
            'company' => 'sometimes|nullable|string|max:255',
            'title' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'website' => 'sometimes|nullable|url|max:2048',
            'location' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
        ]);

        $contact->update($validated);

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(CrmContact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === auth()->id(), 403);
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }

    /**
     * Bulk actions on the selected contacts: add to / remove from a list,
     * delete, or export just those.
     */
    public function bulk(Request $request): RedirectResponse|StreamedResponse|\Illuminate\Http\Response
    {
        $validated = $request->validate([
            'action' => 'required|in:add_to_list,remove_from_list,delete,export',
            'contact_ids' => 'required|array|min:1|max:1000',
            'contact_ids.*' => 'integer',
            'list_id' => ['required_if:action,add_to_list,remove_from_list', 'nullable', 'integer', Rule::exists('crm_lists', 'id')->where('user_id', auth()->id())],
        ], [
            'contact_ids.required' => 'Tick at least one contact first.',
            'list_id.required_if' => 'Choose a list for that action.',
        ]);

        $contacts = auth()->user()->crmContacts()->whereIn('id', $validated['contact_ids'])->get();
        $count = $contacts->count();

        switch ($validated['action']) {
            case 'add_to_list':
                $list = CrmList::findOrFail($validated['list_id']);
                $added = $contacts->filter(fn (CrmContact $c) => $list->addContact($c))->count();

                return back()->with('success', "{$added} contact(s) added to {$list->name}".($added < $count ? ' ('.($count - $added).' were already on it)' : '').'.');

            case 'remove_from_list':
                $list = CrmList::findOrFail($validated['list_id']);
                $list->contacts()->detach($contacts->pluck('id'));

                return back()->with('success', "{$count} contact(s) removed from {$list->name}.");

            case 'delete':
                CrmContact::whereIn('id', $contacts->pluck('id'))->delete();

                return back()->with('success', "{$count} contact(s) deleted.");

            default:
                return $this->csv($contacts, 'affilstack-selected-contacts.csv');
        }
    }

    public function export(Request $request)
    {
        $query = auth()->user()->crmContacts()->where('is_sandbox', false);

        if ($request->filled('list')) {
            $query->whereHas('lists', fn ($q) => $q->where('crm_lists.id', (int) $request->query('list')));
        }

        return $this->csv($query->with('lists')->get(), 'affilstack-contacts.csv');
    }

    /**
     * @param  Collection<int, CrmContact>  $contacts
     */
    protected function csv(Collection $contacts, string $filename): \Illuminate\Http\Response
    {
        $csv = "Name,Company,Title,Email,Phone,Website,Location,Status,Source,Lists,Tags,Lead score,Score reason,Email check\n";
        foreach ($contacts as $c) {
            $csv .= collect([$c->name, $c->company, $c->title, $c->email, $c->phone, $c->website, $c->location, $c->status, $c->source, $c->lists->pluck('name')->implode('; '), implode('; ', $c->tags ?? []), $c->lead_score, $c->raw_data['score_reason'] ?? '', $c->raw_data['email_status'] ?? ''])
                ->map(fn ($v) => '"'.str_replace('"', '""', $this->neutralizeFormula((string) $v)).'"')
                ->implode(',')."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Contacts come from Google Maps listings and user input; a cell that
     * starts with = + - @ (or a tab/CR) is run as a formula when the export
     * is opened in Excel/Sheets. A leading apostrophe keeps it plain text.
     */
    protected function neutralizeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @return Builder<CrmContact>|HasMany<CrmContact, User>
     */
    protected function filteredContacts(Request $request, User $user, ?CrmList $list)
    {
        $query = $user->crmContacts();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('company', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (in_array($request->query('status'), self::STATUSES, true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('status') === 'replied') {
            $query->whereNotNull('replied_at');
        }

        if (in_array($request->query('source'), self::SOURCES, true)) {
            $query->where('source', $request->query('source'));
        }

        if ($list) {
            $query->whereHas('lists', fn ($q) => $q->where('crm_lists.id', $list->id));
        } elseif ($request->query('list') === 'none') {
            $query->whereDoesntHave('lists');
        }

        return $query;
    }

    /**
     * @return array{total: int, by_status: Collection<string, int>, sent_this_month: int, open_rate_this_month: int|null, replied: int}
     */
    protected function stats(User $user): array
    {
        $statusCounts = $user->crmContacts()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sendsThisMonth = CrmEmailSend::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->get(['status', 'opened_at']);
        $sentThisMonth = $sendsThisMonth->where('status', 'sent')->count();
        $openedThisMonth = $sendsThisMonth->whereNotNull('opened_at')->count();

        return [
            'total' => (int) $statusCounts->sum(),
            'by_status' => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) $statusCounts->get($status, 0)]),
            'sent_this_month' => $sentThisMonth,
            'open_rate_this_month' => $sentThisMonth > 0 ? (int) round($openedThisMonth / $sentThisMonth * 100) : null,
            'replied' => $user->crmContacts()->whereNotNull('replied_at')->count(),
        ];
    }

    /**
     * @return Collection<int, CrmList>
     */
    protected function listsFor(User $user): Collection
    {
        return $user->crmLists()->with('offer:id,product_name')->withCount('contacts')->orderByRaw('offer_id is null')->orderBy('name')->get();
    }

    /**
     * Every researched product gets its own list, so a user can file
     * contacts for it before saving the first one.
     */
    protected function ensureProductLists(User $user): void
    {
        $missing = $user->offers()->where('is_sandbox', false)->whereDoesntHave('crmLists')->get();

        foreach ($missing as $offer) {
            CrmList::forOffer($offer);
        }
    }
}
