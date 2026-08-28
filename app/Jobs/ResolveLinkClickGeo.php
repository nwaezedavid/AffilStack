<?php

namespace App\Jobs;

use App\Models\LinkClick;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fills in a link click's country after the fact, so the public /go/{code}
 * redirect never waits on an external geolocation API. Uses ip-api.com's
 * free tier (no key required) — deliberately best-effort: any failure just
 * leaves country null rather than retrying or failing the job loudly, since
 * a missing country on one click is never worth noise in the queue.
 */
class ResolveLinkClickGeo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public LinkClick $click) {}

    public function handle(): void
    {
        $ip = $this->click->ip_address;

        if (! $ip || $this->isPrivateOrLocal($ip)) {
            return;
        }

        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country',
            ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $this->click->update(['country' => $response->json('country')]);
            }
        } catch (Throwable $e) {
            Log::info('Link click geolocation lookup failed, leaving country blank.', [
                'link_click_id' => $this->click->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function isPrivateOrLocal(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
