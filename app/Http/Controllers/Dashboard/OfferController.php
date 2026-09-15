<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\ResearchClip;
use App\Services\Compliance\DisclosureService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use App\Services\Video\HeyGenClient;
use App\Services\Video\VideoGenerationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'clip_id' => 'nullable|integer',
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

        // "Create offer from clip" (item 11's browser extension): attach the
        // clip that prompted this offer, but only if it's still this user's.
        if (! empty($validated['clip_id'])) {
            ResearchClip::where('id', $validated['clip_id'])
                ->where('user_id', auth()->id())
                ->update(['offer_id' => $offer->id]);
        }

        return redirect()->route('offers.show', $offer)->with('success', "Research queued — we'll notify you the moment it's ready.");
    }

    public function show(Offer $offer): View
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $offer->load('generations.emailSends', 'researchClips');
        $contacts = auth()->user()->crmContacts()->orderBy('name')->get();

        $ugcVideoReady = HeyGenSetting::current()->isReady();
        $ugcAvatars = $ugcVideoReady ? $this->ugcVideoAvatars() : [];
        $ugcVoices = $ugcVideoReady ? $this->ugcVideoVoices() : [];

        return view('dashboard.offers.show', compact('offer', 'contacts', 'ugcVideoReady', 'ugcAvatars', 'ugcVoices'));
    }

    /**
     * @return array<int, array{id: string, name: string, gender: ?string, preview_image_url: ?string}>
     */
    protected function ugcVideoAvatars(): array
    {
        try {
            return Cache::remember('heygen:avatars', now()->addHours(6), fn () => (new HeyGenClient(
                (string) HeyGenSetting::current()->credential('api_key'),
            ))->listAvatars());
        } catch (VideoGenerationException) {
            return [];
        }
    }

    /**
     * @return array<int, array{id: string, name: string, language: ?string, gender: ?string}>
     */
    protected function ugcVideoVoices(): array
    {
        try {
            return Cache::remember('heygen:voices', now()->addHours(6), fn () => (new HeyGenClient(
                (string) HeyGenSetting::current()->credential('api_key'),
            ))->listVoices());
        } catch (VideoGenerationException) {
            return [];
        }
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
