<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Services\Leads\GoogleMapsException;
use App\Services\Leads\GoogleMapsLeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Core feature 7 (Phase 2): Google Maps local lead-finding. Owner-only —
 * absent from config('agency.seat_allowed_routes'), same as the CRM itself
 * (a team seat's email-nurture card already explains CRM contacts are
 * account-wide, not per-seat).
 */
class LeadFinderController extends Controller
{
    /**
     * Reads niche/location off the query string so a link from the Offer
     * Research "recommended next step" card (see offers/show.blade.php) can
     * land here pre-filled with the AI's own suggested search — and, since
     * both values already came from a real recommendation rather than a
     * guess, runs that search immediately instead of making the user press
     * "Search" again on an already-filled-in form. A search typed by hand
     * still always goes through the explicit search() action below.
     */
    public function index(Request $request, GoogleMapsLeadService $service): View
    {
        $niche = (string) $request->query('niche', '');
        $location = (string) $request->query('location', '');
        $results = [];
        $importedPlaceIds = [];
        $autoSearchError = null;

        if ($niche !== '' && $location !== '' && auth()->user()->canUseChannel('google_maps')) {
            try {
                $results = $service->search($niche, $location);
                $importedPlaceIds = $this->importedPlaceIds();
            } catch (GoogleMapsException $e) {
                $autoSearchError = $e->getMessage();
            }
        }

        return view('dashboard.leads.index', [
            'results' => $results,
            'importedPlaceIds' => $importedPlaceIds,
            'niche' => $niche,
            'location' => $location,
            'autoSearchError' => $autoSearchError,
        ]);
    }

    public function search(Request $request, GoogleMapsLeadService $service): View|RedirectResponse
    {
        if (! auth()->user()->canUseChannel('google_maps')) {
            return back()->with('error', 'Local lead-finding isn\'t included in your current plan — upgrade to unlock it.');
        }

        $validated = $request->validate([
            'niche' => 'required|string|max:255',
            'location' => 'required|string|max:255',
        ]);

        try {
            $results = $service->search($validated['niche'], $validated['location']);
        } catch (GoogleMapsException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return view('dashboard.leads.index', [
            'results' => $results,
            'importedPlaceIds' => $this->importedPlaceIds(),
            'niche' => $validated['niche'],
            'location' => $validated['location'],
            'autoSearchError' => null,
        ]);
    }

    /**
     * Businesses already imported (by Google's own place_id, not name — two
     * different chains can share a name) are marked rather than hidden, so
     * re-running a search doesn't look broken. Shared by index()'s
     * auto-search and search() so both mark results identically.
     *
     * @return array<int, string>
     */
    protected function importedPlaceIds(): array
    {
        return auth()->user()->crmContacts()
            ->where('source', 'google_maps')
            ->get()
            ->map(fn (CrmContact $c) => $c->raw_data['google_place_id'] ?? null)
            ->filter()
            ->all();
    }

    public function import(Request $request, GoogleMapsLeadService $service): RedirectResponse
    {
        if (! auth()->user()->canUseChannel('google_maps')) {
            return back()->with('error', 'Local lead-finding isn\'t included in your current plan — upgrade to unlock it.');
        }

        if (auth()->user()->crmContactLimitReached()) {
            return back()->with('error', 'You\'ve reached your plan\'s CRM contact limit. Upgrade to import more.');
        }

        $validated = $request->validate([
            'place_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
        ]);

        $alreadyImported = auth()->user()->crmContacts()
            ->where('source', 'google_maps')
            ->get()
            ->contains(fn (CrmContact $c) => ($c->raw_data['google_place_id'] ?? null) === $validated['place_id']);

        if ($alreadyImported) {
            return back()->with('error', 'That business is already in your CRM.');
        }

        try {
            $details = $service->details($validated['place_id']);
        } catch (GoogleMapsException $e) {
            return back()->with('error', $e->getMessage());
        }

        CrmContact::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'company' => $validated['name'],
            'phone' => $details['phone'],
            'website' => $details['website'],
            'location' => $validated['address'] ?? null,
            'source' => 'google_maps',
            'status' => 'new',
            'raw_data' => [
                'google_place_id' => $validated['place_id'],
                'type' => $validated['type'] ?? null,
            ],
        ]);

        return back()->with('success', "\"{$validated['name']}\" added to your CRM.");
    }
}
