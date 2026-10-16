<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Services\Networks\NetworkException;
use App\Services\Networks\NetworkLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * A product's link source: the affiliate network's tracked link (made by
 * the network, deep-linked to the product page where allowed) or the
 * customer's own affiliate link, which is kept as the backup.
 */
class OfferNetworkLinkController extends Controller
{
    public function store(Request $request, Offer $offer, NetworkLinkService $links): RedirectResponse
    {
        $this->authorizeOffer($request, $offer);

        $data = $request->validate(['program_id' => ['required', 'integer']]);

        $program = NetworkProgram::query()
            ->whereKey($data['program_id'])
            ->whereHas('connection', fn ($query) => $query->where('user_id', $offer->user_id)->active())
            ->first();

        if (! $program) {
            return back()->with('error', 'Choose one of the programs from your connected networks.');
        }

        try {
            $links->attach($offer, $program);
        } catch (InvalidArgumentException|NetworkException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Now using your '.$program->connection->label().' tracked link for '.$offer->product_name.'. Every link in your content follows automatically.');
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        $this->authorizeOffer($request, $offer);

        $useNetwork = $request->boolean('use_network_link');

        if ($useNetwork && blank($offer->network_link)) {
            return back()->with('error', 'Choose a network program for this product first.');
        }

        if (! $useNetwork && blank($offer->affiliate_link)) {
            // Falling back to the bare product page would earn nothing.
            return back()->with('error', 'Add your own affiliate link to this product before switching away from the network link.');
        }

        $offer->update(['use_network_link' => $useNetwork]);

        return back()->with('success', $useNetwork ? 'Using the network\'s tracked link again.' : 'Using your own affiliate link. The network link is kept if you want to switch back.');
    }

    protected function authorizeOffer(Request $request, Offer $offer): void
    {
        abort_unless($offer->user_id === $request->user()->id, 403, 'Only the account owner can change a product\'s link.');
    }
}
