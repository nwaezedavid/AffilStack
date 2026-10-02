<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmList;
use App\Models\Offer;
use App\Services\Social\InstagramPublishingService;
use App\Services\Social\TikTokPublishingService;
use App\Services\Social\YouTubePublishingService;
use App\Services\Video\HeyGenCatalog;
use App\Support\OfferFeatures;
use App\Support\ResearchPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One dedicated page per built-in feature of a researched product (route
 * offers.feature) — what the feature does, how to use it, its inputs, and
 * this product's history of that feature's content. The actual generation
 * still goes through each module's own POST route/controller, which now
 * redirects back here.
 */
class OfferFeatureController extends Controller
{
    public function show(Request $request, Offer $offer, string $feature, HeyGenCatalog $heygen): View|JsonResponse
    {
        $user = auth()->user();

        abort_unless($offer->isAccessibleBy($user), 403);
        abort_unless(OfferFeatures::exists($feature), 404);

        $offer->load('generations.emailSends');
        $definition = OfferFeatures::get($feature);

        $generations = $feature === 'localize'
            ? $offer->generations->whereNotNull('target_market')
            : $offer->generations->whereIn('module', $definition['modules']);
        $generations = $generations->sortByDesc('created_at')->values();

        // The UGC page shows the latest angle ideas as step 1's picker, so
        // its history leaves finished angle rounds out rather than repeat them.
        $history = $feature === 'ugc'
            ? $generations->reject(fn ($generation) => $generation->module === 'ugc_angles' && $generation->status === 'completed')->values()
            : $generations;

        $data = [
            'offer' => $offer,
            'featureKey' => $feature,
            'feature' => $definition,
            'tone' => OfferFeatures::tone($definition['tone']),
            'generations' => $generations,
            'history' => $history,
            'pending' => $generations->contains('status', 'queued'),
            'planLocked' => ! OfferFeatures::planAllows($user, $feature),
            'seatLocked' => ! OfferFeatures::seatAllows($user, $feature),
            'research' => new ResearchPresenter($offer, $user),
            'tiles' => OfferFeatures::tilesFor($user, $offer),
            'contacts' => collect(),
            'ugcVideoReady' => false,
            'ugcAvatars' => [],
            'ugcVoices' => [],
            'socialConnections' => collect(),
            'publishProviders' => [],
            'localizable' => collect(),
            'prospectList' => null,
            'prospectCount' => 0,
        ];

        if ($feature === 'nurture' && ! $user->isSeat()) {
            $data['contacts'] = $user->crmContacts()->orderBy('name')->get();
        }

        if ($feature === 'ugc') {
            $data['ugcVideoReady'] = $heygen->isReady();
            $data['ugcAvatars'] = $heygen->avatars();
            $data['ugcVoices'] = $heygen->voices();
            $data['socialConnections'] = $user->socialConnections()->get()->keyBy('provider');
            $data['publishProviders'] = $this->publishProviders();
        }

        if ($feature === 'maps' && ! $user->isSeat() && $offer->user_id === $user->id) {
            $data['prospectList'] = CrmList::forOffer($offer);
            $data['prospectCount'] = $data['prospectList']->contacts()->count();
        }

        if ($feature === 'localize') {
            $data['localizable'] = $offer->generations
                ->where('status', 'completed')
                ->whereNull('target_market')
                ->whereIn('module', config('localization.localizable_modules'))
                ->sortByDesc('created_at')
                ->values();
        }

        // Polled while something on this page is still generating: just the
        // history list, re-rendered, so it updates without a page reload.
        if ($request->query('fragment') === 'history') {
            return response()->json([
                'pending' => $data['pending'],
                'html' => view('dashboard.offers.features._history', $data)->render(),
            ]);
        }

        return view('dashboard.offers.feature', $data);
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
}
