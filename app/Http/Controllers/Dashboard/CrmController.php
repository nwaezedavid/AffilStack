<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function index(): View
    {
        $contacts = auth()->user()->crmContacts()->latest()->paginate(20);

        return view('dashboard.crm.index', compact('contacts'));
    }

    public function store(Request $request): RedirectResponse
    {
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
            'Content-Disposition' => 'attachment; filename="affilistack-contacts.csv"',
        ]);
    }
}
