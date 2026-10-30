<?php

namespace App\Services\Agents\Customer;

use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Follows an affiliate link the way a visitor's browser would (up to five
 * redirects, each hop checked against OutboundUrlGuard and pinned to its
 * vetted IP) and reports whether it ends on a working page.
 */
class LinkChecker
{
    /**
     * @return array{status: string, code: int, final_url: string} status: ok, broken or unknown
     */
    public function check(string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop <= 5; $hop++) {
            try {
                $target = OutboundUrlGuard::resolveSafeIp($current);
            } catch (InvalidArgumentException) {
                // Unresolvable domains are dead links; private addresses are never fetched.
                return ['status' => str_contains($current, '://') ? 'broken' : 'unknown', 'code' => 0, 'final_url' => $current];
            }

            try {
                $response = Http::timeout(12)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AffilStackLinkCheck/1.0)'])
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                    ->get($current);
            } catch (Throwable) {
                return ['status' => 'unknown', 'code' => 0, 'final_url' => $current];
            }

            if ($response->redirect() && ($location = $response->header('Location'))) {
                $current = str_starts_with($location, 'http') ? $location : $this->absolute($current, $location);

                continue;
            }

            $code = $response->status();

            return [
                'status' => match (true) {
                    $code < 400 => 'ok',
                    in_array($code, [404, 410], true) || $code >= 500 => 'broken',
                    default => 'unknown', // 401/403/429: often bot protection, not a dead link
                },
                'code' => $code,
                'final_url' => $current,
            ];
        }

        return ['status' => 'broken', 'code' => 0, 'final_url' => $current];
    }

    protected function absolute(string $base, string $location): string
    {
        $parts = parse_url($base);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').'/'.ltrim($location, '/');
    }
}
