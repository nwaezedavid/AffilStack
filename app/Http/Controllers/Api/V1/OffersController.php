<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offers, via the API (task #6). Every endpoint routes through the exact
 * same scoping the dashboard uses — visibleOffers() (a shared-plan seat's
 * token sees the whole team's offers, everyone else sees just their own)
 * and Offer::isAccessibleBy() for a single record — so a token can never
 * see more than its owner could see by logging in themselves.
 *
 * API roadmap item #5 (sandbox mode): every method also scopes by
 * is_sandbox to match the calling token, mirroring Stripe's test-mode/
 * live-mode split — a sandbox token only ever sees sandbox rows, a live
 * token only ever sees live ones, so the two can never leak into each
 * other's view.
 */
class OffersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        $offers = $request->user()->visibleOffers()
            ->where('is_sandbox', $isSandbox)
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($offers);
    }

    public function show(Request $request, Offer $offer): JsonResponse
    {
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        abort_unless($offer->isAccessibleBy($request->user()) && $offer->is_sandbox === $isSandbox, 404);

        return response()->json($offer);
    }

    public function store(Request $request, OfferResearchService $service): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_url' => 'required|url|max:2048',
            'affiliate_network' => 'required|string|max:255',
            'affiliate_link' => 'nullable|url|max:2048',
        ]);

        $token = $request->attributes->get('apiToken');

        // API roadmap item #5 — a sandbox token never touches the real
        // (paid) research pipeline: no credits spent, no AI call, no queue
        // job dispatched, and (see MeterApiUsage) no wallet charge either.
        // It gets back an immediately-"ready" offer with deterministic
        // canned data instead, shaped exactly like a real one, so
        // integration code written against sandbox data needs no changes
        // to work against live data.
        if ($token?->is_sandbox) {
            $offer = Offer::create([
                'user_id' => $request->user()->billableUser()->id,
                'is_sandbox' => true,
                'product_name' => $validated['product_name'],
                'product_url' => $validated['product_url'],
                'affiliate_network' => $validated['affiliate_network'],
                'affiliate_link' => $validated['affiliate_link'] ?? null,
                'status' => 'ready',
                ...$this->sandboxResearchFields(),
            ]);

            return response()->json($offer, 201);
        }

        try {
            $offer = $service->queue(
                $request->user(),
                $validated['product_name'],
                $validated['product_url'],
                $validated['affiliate_network'],
                $validated['affiliate_link'] ?? null,
            );
        } catch (InsufficientCreditsException) {
            return response()->json(['message' => 'Not enough credits for offer research.'], 402);
        }

        return response()->json($offer, 201);
    }

    /**
     * Deterministic canned research output for a sandbox-token offer,
     * shaped exactly like OfferResearchService::research()'s real AI
     * output (same column mapping: where_to_find imploded to a string,
     * the full structure also kept in research_data).
     *
     * @return array<string, mixed>
     */
    private function sandboxResearchFields(): array
    {
        $result = [
            'ideal_customer_summary' => 'Sandbox data: a small-business owner evaluating this product to save time on a recurring task, with budget authority and a 2-4 week decision window.',
            'pain_points' => ['Doing this manually today', 'Existing tools are too expensive or complex', 'Needs something the team can adopt without training'],
            'where_to_find' => ['r/smallbusiness', 'Indie Hackers', 'Relevant LinkedIn industry groups', 'Google search for the problem this solves'],
            'recommended_channel' => 'linkedin',
            'recommended_channel_reason' => 'Sandbox data: this is a placeholder recommendation, not a real analysis of the product you submitted.',
            'recommended_angle' => 'Sandbox data: "Here\'s how we solved this problem in under 10 minutes."',
            'secondary_channel' => 'blog',
            'suggested_maps_niche' => '',
            'suggested_maps_location' => '',
        ];

        return [
            'ideal_customer_summary' => $result['ideal_customer_summary'],
            'where_to_find' => implode("\n", $result['where_to_find']),
            'recommended_channel' => $result['recommended_channel'],
            'recommended_angle' => $result['recommended_angle'],
            'research_data' => $result,
            'suggested_maps_niche' => null,
            'suggested_maps_location' => null,
        ];
    }
}
