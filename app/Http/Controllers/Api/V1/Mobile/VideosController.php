<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\UgcController;
use App\Http\Resources\Mobile\MobileGenerationResource;
use App\Models\Generation;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\QuickVideoService;
use App\Services\Video\VideoStyles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * One-click videos in the phone app — the same three styles, credit
 * prices, plan check and team-seat rule as the dashboard's Quick Video
 * (UgcController::quick), with an optional product photo from the camera
 * or gallery.
 */
class VideosController extends Controller
{
    public function styles(QuickVideoService $videos): JsonResponse
    {
        return response()->json([
            'data' => collect($videos->availableStyles())->map(fn (array $style, string $key): array => [
                'style' => $key,
                'label' => $style['label'],
                'description' => $style['description'],
                'credits' => (int) $style['credits'],
                'duration_seconds' => (int) $style['duration'],
                'uses_product_photo' => VideoStyles::usesHiggsfield($key),
                'available' => (bool) $style['available'],
            ])->values(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $videos = Generation::query()
            ->where('user_id', $request->user()->id)
            ->where('module', 'ugc_video')
            ->with('offer:id,product_name')
            ->latest()
            ->paginate(20);

        return MobileGenerationResource::collection($videos);
    }

    public function store(Request $request, Offer $offer, QuickVideoService $videos): JsonResponse
    {
        $user = $request->user();
        abort_unless($offer->isAccessibleBy($user) && ! $offer->is_sandbox, 404);

        if (! $user->canUseChannel('ugc')) {
            return response()->json(['message' => 'Videos aren\'t included in your current plan.'], 403);
        }

        if ($user->isSeat()) {
            return response()->json(['message' => 'Ask your account owner to create videos — team seats can write and edit scripts.'], 403);
        }

        $validated = $request->validate([
            'style' => ['required', 'string', Rule::in(array_keys(VideoStyles::STYLES))],
            'script' => ['nullable', 'string', 'min:20', 'max:'.UgcController::MAX_VIDEO_SCRIPT_CHARACTERS],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=300,min_height=300'],
        ]);

        try {
            $video = $videos->queue($user, $offer, $validated['style'], [
                'script' => $validated['script'] ?? null,
                'image' => $request->file('product_image'),
            ]);
        } catch (InsufficientCreditsException) {
            return response()->json(['message' => 'Not enough credits to create this video.'], 402);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new MobileGenerationResource($video->load('offer:id,product_name')))->response()->setStatusCode(201);
    }
}
