<?php

namespace App\Http\Controllers;

use App\Jobs\RunQuickVideo;
use App\Models\Generation;
use App\Models\VideoStudioSetting;
use App\Services\Modules\QuickVideoService;
use App\Services\Video\HiggsfieldClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Higgsfield's "your video is ready" callback. Only used when the admin has
 * saved a webhook signing secret: every delivery is verified (Standard
 * Webhooks, HMAC-SHA256) before anything is trusted. Without it, the
 * CheckHiggsfieldVideo polling job does the same work a little later.
 */
class HiggsfieldWebhookController extends Controller
{
    public const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, QuickVideoService $service): JsonResponse
    {
        $secret = VideoStudioSetting::current()->higgsfieldWebhookSecret();

        if (! $secret || ! $this->verify($request, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $requestId = (string) $request->json('request_id');

        $video = $requestId === '' ? null : Generation::query()
            ->where('module', 'ugc_video')
            ->where('status', 'queued')
            ->where('output_meta->higgsfield_request_id', $requestId)
            ->first();

        if ($video && $service->checkHiggsfield($video, HiggsfieldClient::parseResult((array) $request->json()->all()))) {
            RunQuickVideo::notifyIfFinished($video->refresh());
        }

        return response()->json(['received' => true]);
    }

    protected function verify(Request $request, string $secret): bool
    {
        $id = (string) $request->header('webhook-id');
        $timestamp = (string) $request->header('webhook-timestamp');
        $signatures = (string) $request->header('webhook-signature');

        if ($id === '' || ! ctype_digit($timestamp) || $signatures === '' || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);

        if ($key === false) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.".$request->getContent(), $key, true));

        foreach (explode(' ', $signatures) as $signature) {
            [$version, $value] = array_pad(explode(',', $signature, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }
}
