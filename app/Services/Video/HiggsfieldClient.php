<?php

namespace App\Services\Video;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Higgsfield's developer API (docs.higgsfield.ai) over Laravel's HTTP
 * client. Generation is asynchronous: submit() queues a request and
 * returns its id, status() reports it until it's completed, failed or
 * moderated. Only successful requests are billed by Higgsfield.
 */
class HiggsfieldClient
{
    public const BASE_URL = 'https://api.higgsfield.ai';

    public function __construct(
        protected string $keyId,
        protected string $keySecret,
        protected int $timeout = 30,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, request_id: ?string, message: string}
     */
    public function submit(string $model, array $params, ?string $webhookUrl = null): array
    {
        $path = '/'.trim($model, '/').($webhookUrl ? '?hf_webhook='.rawurlencode($webhookUrl) : '');

        try {
            $response = $this->client()->post($path, $params);
        } catch (Throwable $e) {
            return ['success' => false, 'request_id' => null, 'message' => 'Could not reach Higgsfield: '.$e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'request_id' => null, 'message' => $this->errorMessage($response)];
        }

        $requestId = $response->json('request_id');

        return $requestId
            ? ['success' => true, 'request_id' => (string) $requestId, 'message' => 'Queued at Higgsfield.']
            : ['success' => false, 'request_id' => null, 'message' => 'Higgsfield did not return a request id.'];
    }

    /**
     * @return array{status: string, video_url: ?string, message: ?string} status: queued, in_progress, completed, failed, nsfw, canceled or unknown
     */
    public function status(string $requestId): array
    {
        try {
            $response = $this->client()->get('/requests/'.rawurlencode($requestId).'/status');
        } catch (Throwable $e) {
            return ['status' => 'unknown', 'video_url' => null, 'message' => 'Could not reach Higgsfield: '.$e->getMessage()];
        }

        if ($response->failed()) {
            // A blip shouldn't fail the video; a missing request should.
            return ['status' => $response->status() === 404 ? 'failed' : 'unknown', 'video_url' => null, 'message' => $this->errorMessage($response)];
        }

        return self::parseResult((array) $response->json());
    }

    /**
     * Reads a status response or a webhook delivery (which nests the
     * output under "payload").
     *
     * @param  array<string, mixed>  $data
     * @return array{status: string, video_url: ?string, message: ?string}
     */
    public static function parseResult(array $data): array
    {
        $video = data_get($data, 'video.url')
            ?? data_get($data, 'payload.video.url')
            ?? data_get($data, 'payload.video.0.url')
            ?? data_get($data, 'video.0.url');

        $error = data_get($data, 'error');

        return [
            'status' => (string) ($data['status'] ?? 'unknown'),
            'video_url' => is_string($video) ? $video : null,
            'message' => is_string($error) ? $error : (is_array($error) ? (string) ($error['message'] ?? json_encode($error)) : null),
        ];
    }

    /**
     * The USD price of one request with these parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array{success: bool, usd: ?float, message: string}
     */
    public function estimate(string $model, array $params): array
    {
        try {
            $response = $this->client()->post('/estimate/'.trim($model, '/'), $params);
        } catch (Throwable $e) {
            return ['success' => false, 'usd' => null, 'message' => 'Could not reach Higgsfield: '.$e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'usd' => null, 'message' => $this->errorMessage($response)];
        }

        $usd = $response->json('usd') ?? $response->json('cost') ?? $response->json('price') ?? $response->json('amount') ?? $response->json('estimate.usd');

        return is_numeric($usd)
            ? ['success' => true, 'usd' => (float) $usd, 'message' => 'Higgsfield accepted the key. Estimated cost: US$'.number_format((float) $usd, 2).' per video.']
            : ['success' => true, 'usd' => null, 'message' => 'Higgsfield accepted the key.'];
    }

    protected function errorMessage(Response $response): string
    {
        $detail = $response->json('detail') ?? $response->json('error.message') ?? $response->json('message') ?? $response->json('error');

        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return match ($response->status()) {
            401, 403 => 'Higgsfield did not accept this API key. Check the Key ID and Secret.',
            402 => 'Your Higgsfield API balance is too low. Add funds at console.higgsfield.ai.',
            429 => 'Higgsfield is busy (rate limit reached). Try again shortly.',
            default => trim("Higgsfield error (HTTP {$response->status()}). ".(string) $detail),
        };
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Authorization' => "Key {$this->keyId}:{$this->keySecret}"]);
    }
}
