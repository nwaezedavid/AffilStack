<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Compliance\DisclosureService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(): View
    {
        $offers = auth()->user()->offers()->latest()->paginate(10);

        return view('dashboard.offers.index', compact('offers'));
    }

    public function create(): View
    {
        return view('dashboard.offers.create');
    }

    public function store(Request $request, OfferResearchService $service): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_url' => 'required|url|max:2048',
            'affiliate_network' => 'required|string|max:255',
        ]);

        try {
            $offer = $service->queue(
                auth()->user(),
                $validated['product_name'],
                $validated['product_url'],
                $validated['affiliate_network'],
            );
        } catch (InsufficientCreditsException $e) {
            return back()->withInput()->with('error', 'Not enough credits for offer research. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "Research queued — we'll notify you the moment it's ready.");
    }

    public function show(Offer $offer): View
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $offer->load('generations');
        $contacts = auth()->user()->crmContacts()->orderBy('name')->get();

        return view('dashboard.offers.show', compact('offer', 'contacts'));
    }

    public function updateDisclosure(Request $request, Offer $offer, DisclosureService $disclosure): RedirectResponse
    {
        $this->authorizeOwner($offer);

        $validated = $request->validate([
            'disclosure_country' => 'required|string|in:'.implode(',', array_keys($disclosure->countries())),
        ]);

        $offer->update($validated);

        return back()->with('success', 'Disclosure jurisdiction updated — it applies to this offer\'s content from now on.');
    }

    protected function authorizeOwner(Offer $offer): void
    {
        abort_unless($offer->user_id === auth()->id(), 403);
    }
}
