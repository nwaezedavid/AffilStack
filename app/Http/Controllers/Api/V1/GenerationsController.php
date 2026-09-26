<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: generated content, via the API (task #6). Write access
 * (regenerating, marking published) stays a dashboard-only action for
 * now — every content module already has its own credit/plan gating that
 * isn't worth re-deriving here, so automation reads finished content out
 * rather than triggering new generations directly through this endpoint.
 */
class GenerationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Sandbox tokens only ever see sandbox rows (and live tokens only
        // live ones) — a generation belongs to whichever its offer is.
        $isSandbox = (bool) $request->attributes->get('apiToken')?->is_sandbox;

        $generations = $request->user()->visibleGenerations()
            ->whereHas('offer', fn ($query) => $query->where('is_sandbox', $isSandbox))
            ->when($request->filled('offer_id'), fn ($query) => $query->where('offer_id', $request->integer('offer_id')))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($generations);
    }

    public function show(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->offer?->isAccessibleBy($request->user()), 404);
        abort_unless((bool) $generation->offer->is_sandbox === (bool) $request->attributes->get('apiToken')?->is_sandbox, 404);

        return response()->json($generation);
    }
}
