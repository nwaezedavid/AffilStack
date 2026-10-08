<?php

namespace App\Jobs;

use App\Models\AnalyticsSession;
use App\Support\Analytics\IpPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Agent 04: fills in an analytics session's country/city after the fact,
 * using the same free ip-api.com lookup as cloaked-link clicks
 * (ResolveLinkClickGeo) and the checkout country resolver. The raw IP only
 * ever lives in this job's payload — which is encrypted in the jobs table
 * (ShouldBeEncrypted) — and is discarded once the lookup is done; the
 * analytics tables never store it. Best-effort: any failure leaves the
 * location blank.
 */
class ResolveAnalyticsSessionGeo implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $sessionId, public string $ip) {}

    public function handle(): void
    {
        if (! IpPrivacy::isPublic($this->ip)) {
            return;
        }

        $session = AnalyticsSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $location = Cache::remember('analytics_geo:'.IpPrivacy::hash($this->ip), now()->addHours(12), function (): array {
            try {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$this->ip}", ['fields' => 'status,countryCode,city']);

                if ($response->successful() && $response->json('status') === 'success') {
                    return ['country' => strtoupper((string) $response->json('countryCode')) ?: null, 'city' => $response->json('city') ?: null];
                }
            } catch (Throwable) {
                // Best-effort, same as ResolveLinkClickGeo.
            }

            return ['country' => null, 'city' => null];
        });

        if (! $location['country']) {
            return;
        }

        $session->update(['country' => substr($location['country'], 0, 2), 'city' => $location['city'] ? mb_substr($location['city'], 0, 100) : null]);

        $session->visitor()->whereNull('country')->update(['country' => substr($location['country'], 0, 2), 'city' => $location['city'] ? mb_substr($location['city'], 0, 100) : null]);
    }
}
