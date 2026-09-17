<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\ResearchClip;
use App\Services\Compliance\DisclosureService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use App\Services\Social\InstagramPublishingService;
use App\Services\Social\TikTokPublishingService;
use App\Services\Social\YouTubePublishingService;
use App\Services\Video\HeyGenClient;
use App\Services\Video\VideoGenerationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        if (in_array($request->query('status'), ['researching', 'ready', 'archived'], true)) {
            $query->where('status', $request->query('status'));
        }

        $offers = $query->latest()->paginate(10)->withQueryString();

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

        $socialConnections = auth()->user()->socialConnections()->get()->keyBy('provider');
        $publishProviders = $this->publishProviders();

        return view('dashboard.offers.show', compact('offer', 'contacts', 'ugcVideoReady', 'ugcAvatars', 'ugcVoices', 'socialConnections', 'publishProviders'));
    }

    /**
     * The publish-capable social platforms (task #2) and whether each has
     * approved AffilStack for publishing yet — used by the ugc_video block
     * to decide between a real "Publish" button and a "connect first" /
     * "awaiting approval" state, falling back to the always-available
     * manual download either way.
     *
     * @return array<string, array{label: string, approved: bool}>
     */
    protected function publishProviders(): array
    {
        return collect([
            'youtube' => YouTubePublishingService::class,
            'tiktok' => TikTokPublishingService::class,
            'instagram' => InstagramPublishingService::class,
        ])->map(fn (string $class, string $key) => [
            'label' => ucfirst($key),
            'approved' => app($class)->isApprovedForPublishing(),
        ])->all();
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
