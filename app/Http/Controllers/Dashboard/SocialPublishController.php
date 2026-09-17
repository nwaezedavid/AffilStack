<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\Social\InstagramPublishingService;
use App\Services\Social\SocialPublishProvider;
use App\Services\Social\TikTokPublishingService;
use App\Services\Social\YouTubePublishingService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Task #2: the "Publish" button on a rendered ugc_video generation.
 * Deliberately thin — every real decision (is this platform approved, how
 * to upload) lives in each SocialPublishProvider implementation; this
 * controller only resolves the caption/title, calls publish(), and records
 * the result onto the generation so the UI can show "Published — view on
 * X" afterward. Any failure (not connected, not yet approved, a real API
 * error) surfaces as a plain flash message pointing back at the existing
 * "Download video" link — the fallback always already exists, nothing
 * extra to build for it.
 */
class SocialPublishController extends Controller
{
    /**
     * @return array<string, SocialPublishProvider>
     */
    protected function providers(): array
    {
        return [
            'youtube' => app(YouTubePublishingService::class),
            'tiktok' => app(TikTokPublishingService::class),
            'instagram' => app(InstagramPublishingService::class),
        ];
    }

    public function store(Generation $generation, string $provider): RedirectResponse
    {
        abort_unless($generation->isAccessibleBy(auth()->user()), 403);
        abort_unless($generation->module === 'ugc_video', 404);

        $service = $this->providers()[$provider] ?? null;

        if (! $service) {
            abort(404);
        }

        $connection = auth()->user()->socialConnections()->where('provider', $provider)->first();

        if (! $connection) {
            return back()->with('error', ucfirst($provider).' isn\'t connected — connect it first, or download the video and post it yourself.');
        }

        [$title, $caption] = $this->resolveTitleAndCaption($generation, $provider);

        try {
            $result = $service->publish($connection, $generation, $title, $caption);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $generation->update([
            'output_meta' => [
                ...($generation->output_meta ?? []),
                'published' => [
                    ...($generation->output_meta['published'] ?? []),
                    $provider => ['url' => $result['url'], 'external_id' => $result['external_id'], 'published_at' => now()->toIso8601String()],
                ],
            ],
        ]);

        return back()->with('success', ucfirst($provider).' publish '.($result['url'] ? 'succeeded — '.$result['url'] : 'started — it may take a few minutes to appear.'));
    }

    /**
     * The matching per-platform title/caption from the ugc_content
     * generation this video was rendered from (UgcService's "platforms"
     * array — free-form AI output, so this matches loosely by name), with
     * a sensible generic fallback when no matching block exists. The
     * caption is cloaked exactly like every other content channel — see
     * Offer::cloak().
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveTitleAndCaption(Generation $ugcVideoGeneration, string $provider): array
    {
        $offer = $ugcVideoGeneration->offer;
        $contentGeneration = Generation::find($ugcVideoGeneration->input['content_generation_id'] ?? null);
        $platforms = $contentGeneration?->output_meta['platforms'] ?? [];

        $match = collect($platforms)->first(fn ($p) => str_contains(strtolower($p['platform'] ?? ''), $provider));

        $title = (string) ($match['title'] ?? $offer->product_name);
        $rawCaption = (string) ($match['caption'] ?? "Check out {$offer->product_name}!");

        return [$title, $offer->cloak($rawCaption, $ugcVideoGeneration->module)];
    }
}
