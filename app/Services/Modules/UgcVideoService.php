<?php

namespace App\Services\Modules;

use App\Jobs\RunUgcVideoGeneration;
use App\Models\Generation;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Video\HeyGenClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The UGC video overhaul: turns an already-generated `ugc_content`
 * script's `video_script` into an actual rendered video, via AffilStack's
 * own HeyGen account (see HeyGenSetting/HeyGenClient) — the user picks a
 * stock avatar and voice, spends platform credits, and never needs a
 * HeyGen account of their own.
 *
 * Unlike every other module, this one genuinely takes minutes to finish —
 * generate() submits the job to HeyGen then polls it to completion (or
 * timeout) inside the same queue-worker call, matching how every other
 * module blocks on its (much faster) AI call. Always leaves the
 * Generation in a terminal state and never throws.
 */
class UgcVideoService
{
    protected const MAX_WAIT_MINUTES = 8;

    protected const POLL_INTERVAL_SECONDS = 10;

    public function __construct(protected CreditManager $credits) {}

    public function queue(User $user, Offer $offer, Generation $contentGeneration, string $avatarId, string $voiceId): Generation
    {
        if ($contentGeneration->offer_id !== $offer->id
            || $contentGeneration->module !== 'ugc_content'
            || $contentGeneration->status !== 'completed') {
            throw new InvalidArgumentException('Pick a completed UGC script for this offer to turn into a video.');
        }

        $videoScript = trim((string) ($contentGeneration->output_meta['video_script'] ?? ''));

        if ($videoScript === '') {
            throw new RuntimeException('This UGC script has no spoken-video version to generate from — try regenerating the content.');
        }

        if (! HeyGenSetting::current()->isReady()) {
            throw new RuntimeException('Video generation isn\'t connected yet — ask an admin to connect and verify HeyGen in Content > UGC Video Settings.');
        }

        $cost = (int) config('credits.costs.ugc_video');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to generate a UGC video.");
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => [
                'content_generation_id' => $contentGeneration->id,
                'avatar_id' => $avatarId,
                'voice_id' => $voiceId,
                'video_script' => $videoScript,
            ],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunUgcVideoGeneration::dispatch($generation);

        return $generation;
    }

    /**
     * Called by RunUgcVideoGeneration inside the queue worker. Always
     * leaves the generation in a terminal state and never throws.
     */
    public function generate(Generation $generation): void
    {
        $user = $generation->user;
        $cost = (int) config('credits.costs.ugc_video');

        if (! $this->credits->hasEnough($user, $cost)) {
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);

            return;
        }

        $settings = HeyGenSetting::current();

        if (! $settings->isReady()) {
            $generation->update(['status' => 'failed', 'error_message' => 'Video generation is no longer connected — contact support.']);

            return;
        }

        $client = new HeyGenClient((string) $settings->credential('api_key'));

        $submission = $client->generateVideo(
            (string) $generation->input['avatar_id'],
            (string) $generation->input['voice_id'],
            (string) $generation->input['video_script'],
        );

        if (! $submission['success']) {
            $generation->update(['status' => 'failed', 'error_message' => $submission['message']]);

            return;
        }

        $videoId = $submission['video_id'];
        $result = $this->pollUntilDone($client, $videoId);

        if ($result['status'] !== 'completed' || ! $result['video_url']) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $result['message'] ?? "HeyGen didn't finish this video in time (id {$videoId}) — it may still complete on their side.",
                'output_meta' => ['heygen_video_id' => $videoId],
            ]);

            return;
        }

        $localPath = $this->downloadVideo($result['video_url'], $generation->id);
        $videoUrl = $localPath ? Storage::disk('public')->url($localPath) : $result['video_url'];

        // Spending the credits and marking the generation completed must be
        // atomic: if spend() fails (e.g. a concurrent generation already
        // took the user's last credits between the hasEnough() check above
        // and here — this HeyGen round-trip can take several minutes,
        // leaving plenty of time for exactly that race), the generation
        // must NOT be left "completed" with credits_spent recorded — that
        // would hand out this rendered video for free while the ledger
        // shows nothing charged.
        try {
            DB::transaction(function () use ($generation, $user, $cost, $videoId, $videoUrl) {
                $this->credits->spend($user, $cost, 'ugc_video', $generation);

                $generation->update([
                    'status' => 'completed',
                    'output' => $videoUrl,
                    'output_meta' => ['heygen_video_id' => $videoId, 'video_url' => $videoUrl],
                    'credits_spent' => $cost,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Credit spend failed after a successful UGC video render — video was generated but not charged', [
                'generation_id' => $generation->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);
        }
    }

    /**
     * @return array{status: string, video_url: ?string, message: ?string}
     */
    protected function pollUntilDone(HeyGenClient $client, string $videoId): array
    {
        $deadline = now()->addMinutes(self::MAX_WAIT_MINUTES);

        while (now()->lessThan($deadline)) {
            $status = $client->checkVideoStatus($videoId);

            if (in_array($status['status'], ['completed', 'failed'], true)) {
                return $status;
            }

            $this->wait(self::POLL_INTERVAL_SECONDS);
        }

        return ['status' => 'failed', 'video_url' => null, 'message' => "Timed out waiting for HeyGen to finish rendering (video id {$videoId})."];
    }

    /**
     * A real sleep in production; a no-op under the test runner, so a test
     * exercising the polling loop doesn't actually block for minutes.
     */
    protected function wait(int $seconds): void
    {
        if (! app()->runningUnitTests()) {
            sleep($seconds);
        }
    }

    protected function downloadVideo(string $url, int $generationId): ?string
    {
        try {
            $response = Http::timeout(120)->get($url);

            if ($response->failed()) {
                return null;
            }

            $path = "ugc-videos/{$generationId}.mp4";
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable $e) {
            Log::warning('UGC video: downloading the finished render failed, falling back to HeyGen\'s own URL.', [
                'generation_id' => $generationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
