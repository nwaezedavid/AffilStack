<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class CrmController extends Controller
{
    /**
     * Public, unauthenticated — see routes/web.php and CrmEmailService.
     */
    public function unsubscribe(string $token): View
    {
        $contact = CrmContact::where('unsubscribe_token', $token)->firstOrFail();

        if (! $contact->isUnsubscribed()) {
            $contact->update(['unsubscribed_at' => now()]);
        }

        return view('marketing.crm-unsubscribed', ['senderName' => $contact->user->name]);
    }

    /**
     * The pipeline stats header (task #90): a snapshot, not a full report —
     * cheap enough to compute on every page load for one user's own data.
     */
    public function index(): View
    {
        $user = auth()->user();
        $contacts = $user->crmContacts()->latest()->paginate(20);

        $statusCounts = $user->crmContacts()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sendsThisMonth = CrmEmailSend::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->get(['status', 'opened_at']);
        $sentThisMonth = $sendsThisMonth->where('status', 'sent')->count();
        $openedThisMonth = $sendsThisMonth->whereNotNull('opened_at')->count();

        $stats = [
            'total' => $statusCounts->sum(),
            'by_status' => collect(['new', 'contacted', 'qualified', 'customer', 'unqualified'])
                ->mapWithKeys(fn ($status) => [$status => $statusCounts->get($status, 0)]),
            'sent_this_month' => $sentThisMonth,
            'open_rate_this_month' => $sentThisMonth > 0 ? (int) round($openedThisMonth / $sentThisMonth * 100) : null,
        ];

        return view('dashboard.crm.index', compact('contacts', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()->crmContactLimitReached()) {
            return back()->with('error', 'You\'ve reached your plan\'s CRM contact limit. Upgrade to add more.');
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

        $validated['user_id'] = auth()->id();
        $validated['source'] = 'manual';

        CrmContact::create($validated);

        return back()->with('success', 'Contact saved.');
    }

    public function update(Request $request, CrmContact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'required|in:new,contacted,qualified,customer,unqualified',
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

    public function export()
    {
        $contacts = auth()->user()->crmContacts()->get();

        $csv = "Name,Company,Title,Email,Phone,Website,Location,Status,Source\n";
        foreach ($contacts as $c) {
            $csv .= collect([$c->name, $c->company, $c->title, $c->email, $c->phone, $c->website, $c->location, $c->status, $c->source])
                ->map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"')
                ->implode(',')."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="affilstack-contacts.csv"',
        ]);
    }
}
