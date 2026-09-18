<?php

namespace App\Services\Video;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to HeyGen's v3 API (api.heygen.com) — the UGC video overhaul's
 * rendering engine. Deliberately pins the cheaper "avatar_iii" engine and
 * 720p resolution on every generateVideo() call: HeyGen's own default
 * engine (avatar_iv) runs 3-5x the per-minute cost, and nothing here
 * should silently spend more than the ~$1/min the credit pricing
 * (config/credits.php: ugc_video) was sized against.
 *
 * Video generation is asynchronous on HeyGen's side — generateVideo()
 * only submits the job and returns a video_id; checkVideoStatus() polls
 * it, called in a loop by UgcVideoService rather than here, since only
 * the caller knows how long it's reasonable to keep waiting.
 */
class HeyGenClient
{
    protected const BASE_URL = 'https://api.heygen.com';

    public function __construct(
        protected string $apiKey,
        protected int $timeout = 30,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyApiKey(): array
    {
        try {
            $response = $this->client()->get('/v3/users/me');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach HeyGen: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'HeyGen API key is valid.'];
        }

        return ['success' => false, 'message' => 'HeyGen rejected this key: '.$this->errorMessage($response)];
    }

    /**
     * HeyGen's own remaining-quota endpoint — the number returned is
     * HeyGen's "credits" (roughly: seconds of avatar_iii video render time
     * left on the connected account). Used by Vault, the funding-monitor
     * agent (see FundingHealthChecker), to warn before a "Generate video"
     * click fails mid-render because the account ran dry.
     *
     * @return array{success: bool, remaining: ?int, message: string}
     */
    public function checkBalance(): array
    {
        try {
            $response = $this->client()->get('/v2/user/remaining_quota');
        } catch (Throwable $e) {
            return ['success' => false, 'remaining' => null, 'message' => 'Could not reach HeyGen: '.$e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'remaining' => null, 'message' => $this->errorMessage($response)];
        }

        $remaining = data_get($response->json(), 'data.remaining_quota');

        return $remaining === null
            ? ['success' => false, 'remaining' => null, 'message' => 'HeyGen did not return a remaining_quota value.']
            : ['success' => true, 'remaining' => (int) $remaining, 'message' => 'Balance check succeeded.'];
    }

    /**
     * A flat list of individually pickable stock avatar "looks" — HeyGen
     * technically organizes these into groups first, but a single flat
     * list is a simpler, good-enough picker for this first version of the
     * video overhaul.
     *
     * @return array<int, array{id: string, name: string, gender: ?string, preview_image_url: ?string}>
     */
    public function listAvatars(): array
    {
        $response = $this->client()->get('/v3/avatars/looks', ['limit' => 50]);

        if ($response->failed()) {
            throw new VideoGenerationException('Could not load HeyGen avatars: '.$this->errorMessage($response));
        }

        return collect((array) data_get($response->json(), 'data', []))
            ->map(fn (array $avatar) => [
                'id' => (string) $avatar['id'],
                'name' => (string) ($avatar['name'] ?? $avatar['id']),
                'gender' => $avatar['gender'] ?? null,
                'preview_image_url' => $avatar['preview_image_url'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, language: ?string, gender: ?string}>
     */
    public function listVoices(): array
    {
        $response = $this->client()->get('/v3/voices', ['limit' => 100]);

        if ($response->failed()) {
            throw new VideoGenerationException('Could not load HeyGen voices: '.$this->errorMessage($response));
        }

        return collect((array) data_get($response->json(), 'data', []))
            ->map(fn (array $voice) => [
                'id' => (string) $voice['voice_id'],
                'name' => (string) ($voice['name'] ?? $voice['voice_id']),
                'language' => $voice['language'] ?? null,
                'gender' => $voice['gender'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Submits a script to be spoken by the given avatar/voice — vertical
     * 720p, cheapest engine tier. Returns immediately with a video_id;
     * the render itself typically takes a few minutes (see
     * checkVideoStatus()).
     *
     * @return array{success: bool, video_id: ?string, message: string}
     */
    public function generateVideo(string $avatarId, string $voiceId, string $script): array
    {
        try {
            $response = $this->client()->post('/v3/videos', [
                'type' => 'avatar',
                'avatar_id' => $avatarId,
                'voice_id' => $voiceId,
                'script' => $script,
                'aspect_ratio' => '9:16',
                'resolution' => '720p',
                'output_format' => 'mp4',
                'engine' => ['type' => 'avatar_iii'],
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'video_id' => null, 'message' => 'Could not reach HeyGen: '.$e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'video_id' => null, 'message' => $this->errorMessage($response)];
        }

        $videoId = data_get($response->json(), 'data.video_id');

        return $videoId
            ? ['success' => true, 'video_id' => $videoId, 'message' => 'Video generation started.']
            : ['success' => false, 'video_id' => null, 'message' => 'HeyGen did not return a video id.'];
    }

    /**
     * @return array{status: string, video_url: ?string, message: ?string}
     */
    public function checkVideoStatus(string $videoId): array
    {
        $response = $this->client()->get("/v3/videos/{$videoId}");

        if ($response->failed()) {
            return ['status' => 'failed', 'video_url' => null, 'message' => $this->errorMessage($response)];
        }

        $data = (array) data_get($response->json(), 'data', []);

        return [
            'status' => (string) ($data['status'] ?? 'failed'),
            'video_url' => $data['video_url'] ?? null,
            'message' => $data['failure_message'] ?? null,
        ];
    }

    protected function errorMessage(Response $response): string
    {
        return (string) (data_get($response->json(), 'error.message') ?: $response->body());
    }

    protected function client()
    {
        return Http::withHeaders(['x-api-key' => $this->apiKey])
            ->baseUrl(self::BASE_URL)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
