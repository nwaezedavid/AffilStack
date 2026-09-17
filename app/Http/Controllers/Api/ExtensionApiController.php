<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResearchClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backend for the browser capture extension (item 11) — see routes/api.php
 * and App\Http\Middleware\ApiTokenAuth. ExtensionController (which issues
 * tokens for these routes) is excluded from config('agency.seat_allowed_routes'),
 * so a team seat (item 10) still can't reach this specific surface. A seat
 * CAN now hold a token of its own via the general API's ApiAccessController
 * (task #6) — but that token only ever authenticates the /v1/* routes
 * below, which do their own Offer::isAccessibleBy() scoping, so this
 * controller's own no-offer-scoping-needed assumption still holds for the
 * requests it actually receives.
 */
class ExtensionApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    }

    /**
     * Offers the extension can let the user attach a capture to directly,
     * without leaving the current tab.
     */
    public function offers(Request $request): JsonResponse
    {
        $offers = $request->user()->offers()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'product_name']);

        return response()->json(['offers' => $offers]);
    }

    public function storeClip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:2048', 'url'],
            'title' => ['nullable', 'string', 'max:255'],
            'selected_text' => ['nullable', 'string', 'max:10000'],
            'page_type' => ['required', Rule::in(array_keys(config('extension.page_types')))],
            'offer_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['offer_id'])) {
            $ownsOffer = $request->user()->offers()->whereKey($validated['offer_id'])->exists();

            if (! $ownsOffer) {
                unset($validated['offer_id']);
            }
        }

        $clip = ResearchClip::create([
            'user_id' => $request->user()->id,
            'offer_id' => $validated['offer_id'] ?? null,
            'source_url' => $validated['source_url'],
            'title' => $validated['title'] ?? null,
            'selected_text' => $validated['selected_text'] ?? null,
            'page_type' => $validated['page_type'],
        ]);

        return response()->json(['id' => $clip->id, 'message' => 'Saved to AffilStack.'], 201);
    }
}
