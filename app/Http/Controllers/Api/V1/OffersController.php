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
 */
class OffersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $offers = $request->user()->visibleOffers()
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($offers);
    }

    public function show(Request $request, Offer $offer): JsonResponse
    {
        abort_unless($offer->isAccessibleBy($request->user()), 404);

        return response()->json($offer);
    }

    public function store(Request $request, OfferResearchService $service): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_url' => 'required|url|max:2048',
            'affiliate_network' => 'required|string|max:255',
        ]);

        try {
            $offer = $service->queue(
                $request->user(),
                $validated['product_name'],
                $validated['product_url'],
                $validated['affiliate_network'],
            );
        } catch (InsufficientCreditsException) {
            return response()->json(['message' => 'Not enough credits for offer research.'], 402);
        }

        return response()->json($offer, 201);
    }
}
