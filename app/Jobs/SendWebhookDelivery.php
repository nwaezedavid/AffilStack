<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\OutboundUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Audit gap #7 — sends (or retries) one WebhookDelivery. Retries are
 * self-managed rather than left to Laravel's queue-level retry: each
 * failure updates the delivery row (so a user can see exactly what
 * happened) and re-dispatches itself with a delay from
 * config('webhooks.retry_backoff_minutes'), up to max_attempts, rather
 * than throwing and letting the queue worker's own backoff/tries decide —
 * that would leave the delivery stuck at "pending" with no visible
 * attempt count until the very last failure.
 */
class SendWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(): void
    {
        $endpoint = $this->delivery->endpoint;

        if (! $endpoint || ! $endpoint->is_active) {
            $this->delivery->update(['status' => 'failed']);

            return;
        }

        $body = json_encode([
            'event' => $this->delivery->event,
            'data' => $this->delivery->payload,
            'delivery_id' => $this->delivery->id,
            'timestamp' => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        // Re-vetted at send time and the vetted IP pinned for the connection
        // (see OutboundUrlGuard): the saved hostname could since have been
        // re-pointed at 127.0.0.1 or a private address.
        try {
            $target = OutboundUrlGuard::resolveSafeIp($endpoint->url);
        } catch (InvalidArgumentException $e) {
            $this->delivery->update([
                'status' => 'failed',
                'attempts' => $this->delivery->attempts + 1,
                'response_status' => null,
                'response_body' => 'Blocked: '.$e->getMessage(),
            ]);

            return;
        }

        try {
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [
                    "{$target['host']}:{$target['port']}:".(str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip']),
                ]]])
                ->withBody($body, 'application/json')
                ->withHeaders([
                    'X-AffilStack-Event' => $this->delivery->event,
                    'X-AffilStack-Signature' => $signature,
                ])
                ->post($endpoint->url);

            if ($response->successful()) {
                $this->delivery->update([
                    'status' => 'delivered',
                    'attempts' => $this->delivery->attempts + 1,
                    'response_status' => $response->status(),
                    'response_body' => Str::limit($response->body(), 1000),
                    'delivered_at' => now(),
                ]);

                $endpoint->update(['last_triggered_at' => now()]);

                return;
            }

            $this->retryOrFail($response->status(), $response->body());
        } catch (Throwable $e) {
            $this->retryOrFail(null, $e->getMessage());
        }
    }

    protected function retryOrFail(?int $responseStatus, ?string $responseBody): void
    {
        $attempts = $this->delivery->attempts + 1;
        $maxAttempts = (int) config('webhooks.max_attempts');

        $this->delivery->update([
            'status' => $attempts >= $maxAttempts ? 'failed' : 'pending',
            'attempts' => $attempts,
            'response_status' => $responseStatus,
            'response_body' => Str::limit((string) $responseBody, 1000),
        ]);

        if ($attempts >= $maxAttempts) {
            return;
        }

        $backoffMinutes = config('webhooks.retry_backoff_minutes')[$attempts - 1] ?? 60;

        self::dispatch($this->delivery)->delay(now()->addMinutes($backoffMinutes));
    }
}
