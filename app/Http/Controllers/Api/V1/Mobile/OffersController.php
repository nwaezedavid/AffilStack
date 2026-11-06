<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileGenerationResource;
use App\Http\Resources\Mobile\MobileOfferResource;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Support\Mobile\ContentTypes;
use App\Support\Mobile\SeatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Products in the phone app: the list, one product with its research and
 * content, and "Create content" — through the same visibleOffers() /
 * isAccessibleBy() scoping, module services, credit prices and team-seat
 * rules as the dashboard. Adding a product uses POST /api/v1/offers.
 */
class OffersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q', ''));

        $offers = $request->user()->visibleOffers()
            ->where('is_sandbox', false)
            ->when($search !== '', fn ($query) => $query->where('product_name', 'like', '%'.$search.'%'))
            ->withCount('generations')
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return MobileOfferResource::collection($offers);
    }

    public function show(Request $request, Offer $offer): JsonResponse
    {
        abort_unless($offer->isAccessibleBy($request->user()) && ! $offer->is_sandbox, 404);

        $generations = $offer->generations()
            ->whereIn('module', array_merge(array_keys(ContentTypes::TYPES), ['ugc_video']))
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'offer' => (new MobileOfferResource($offer))->withResearch(),
            'content' => MobileGenerationResource::collection($generations),
            'content_types' => ContentTypes::forUser($request->user()),
        ]);
    }

    public function createContent(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();
        abort_unless($offer->isAccessibleBy($user) && ! $offer->is_sandbox, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentTypes::TYPES))],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $type = ContentTypes::TYPES[$validated['type']];
        SeatAccess::authorize($user, $type['route']);

        if ($type['channel'] !== null && ! $user->canUseChannel($type['channel'])) {
            return response()->json(['message' => "{$type['label']} isn't included in your current plan."], 403);
        }

        try {
            $generation = ContentTypes::queue($validated['type'], $user, $offer, $validated['keyword'] ?? null);
        } catch (InsufficientCreditsException) {
            return response()->json(['message' => "Not enough credits for a {$type['label']}."], 402);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new MobileGenerationResource($generation))->response()->setStatusCode(201);
    }
}
