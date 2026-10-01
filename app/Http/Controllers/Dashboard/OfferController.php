<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\ResearchClip;
use App\Services\Compliance\DisclosureService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use App\Support\OfferHubData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    /**
     * Audit gap #3: this list had no search or filter at all — fine at a
     * handful of offers, painful once someone has dozens. `q` matches the
     * product name; `status` narrows to one of Offer's own status values.
     */
    public function index(Request $request): View
    {
        $query = auth()->user()->visibleOffers();

        if ($search = trim((string) $request->query('q'))) {
            $query->where('product_name', 'like', '%'.$search.'%');
        }

        if (in_array($request->query('status'), ['queued', 'ready', 'failed', 'researching', 'archived'], true)) {
            $query->where('status', $request->query('status'));
        }

        $offers = $query->latest()
            ->withCount([
                'generations as content_count' => fn ($generations) => $generations->where('module', '!=', 'research')->where('status', 'completed'),
            ])
            ->with(['crmLists' => fn ($lists) => $lists->withCount('contacts')])
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.offers.index', compact('offers'));
    }

    public function create(): View
    {
        return view('dashboard.offers.create');
    }

    /**
     * Queues research from the Get Started form (or the standalone create
     * page). A JSON request — the Get Started form's progressive
     * enhancement — gets the new offer's status URL back so the page can
     * show progress and poll without reloading; a plain form post falls
     * back to a normal redirect.
     */
    public function store(Request $request, OfferResearchService $service): RedirectResponse|JsonResponse
    {
        $otherValue = config('affiliate_networks.other_value');

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_url' => 'required|url|max:2048',
            'affiliate_network' => 'required|string|max:255',
            'affiliate_network_other' => 'nullable|string|max:255|required_if:affiliate_network,'.$otherValue,
            'affiliate_link' => 'nullable|url|max:2048',
            'clip_id' => 'nullable|integer',
        ], [
            'affiliate_network_other.required_if' => 'Type the name of the affiliate platform you chose "Other" for.',
        ]);

        $network = $validated['affiliate_network'] === $otherValue
            ? trim((string) $validated['affiliate_network_other'])
            : $validated['affiliate_network'];

        try {
            $offer = $service->queue(
                auth()->user(),
                $validated['product_name'],
                $validated['product_url'],
                $network,
                $validated['affiliate_link'] ?? null,
            );
        } catch (InsufficientCreditsException $e) {
            $message = 'Not enough credits for offer research. Upgrade your plan or buy a credit top-up.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 402)
                : back()->withInput()->with('error', $message);
        }

        // "Create offer from clip" (item 11's browser extension): attach the
        // clip that prompted this offer, but only if it's still this user's.
        if (! empty($validated['clip_id'])) {
            ResearchClip::where('id', $validated['clip_id'])
                ->where('user_id', auth()->id())
                ->update(['offer_id' => $offer->id]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'offer_id' => $offer->id,
                'status' => $offer->status,
                'status_url' => route('offers.research.status', $offer),
                'show_url' => route('offers.show', $offer),
            ], 201);
        }

        $message = "Research queued — we'll notify you the moment it's ready.";

        return $request->input('return_to') === 'dashboard' && ! auth()->user()->isSeat()
            ? redirect()->route('dashboard')->with('success', $message)
            : redirect()->route('offers.show', $offer)->with('success', $message);
    }

    /**
     * The product hub: research results, the prospects panel, every
     * feature as a tile, and recent content.
     */
    public function show(Offer $offer): View
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $offer->load('generations', 'researchClips');

        $recent = $offer->generations
            ->where('module', '!=', 'research')
            ->sortByDesc('created_at')
            ->take(6);

        return view('dashboard.offers.show', OfferHubData::for(auth()->user(), $offer) + [
            'recentGenerations' => $recent,
        ]);
    }

    /**
     * Polled by the results area while research runs. Returns the freshly
     * rendered results block, so the page swaps it in without a reload.
     */
    public function researchStatus(Offer $offer): JsonResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $offer->refresh();
        $context = request()->query('context') === 'hub' ? 'hub' : 'dashboard';

        return response()->json([
            'status' => $offer->status,
            'html' => view('dashboard.offers.partials.results', OfferHubData::for(auth()->user(), $offer) + ['context' => $context])->render(),
        ]);
    }

    /**
     * Retry a failed research run, or refresh an older offer's research to
     * the extended contract — same offer, same credit price.
     */
    public function retryResearch(Request $request, Offer $offer, OfferResearchService $service): RedirectResponse|JsonResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        if ($offer->status === 'queued') {
            return $request->expectsJson()
                ? response()->json(['status' => 'queued', 'status_url' => route('offers.research.status', $offer)])
                : back()->with('success', 'Research is already running.');
        }

        try {
            $service->requeue(auth()->user(), $offer);
        } catch (InsufficientCreditsException) {
            $message = 'Not enough credits for offer research. Upgrade your plan or buy a credit top-up.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 402)
                : back()->with('error', $message);
        }

        return $request->expectsJson()
            ? response()->json(['status' => 'queued', 'status_url' => route('offers.research.status', $offer)])
            : back()->with('success', "Research restarted — we'll notify you the moment it's ready.");
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
