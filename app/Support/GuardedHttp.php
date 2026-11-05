<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * GET a customer-supplied URL safely: every hop (up to three redirects) is
 * checked with OutboundUrlGuard and the connection pinned to the vetted
 * IP, so the address can never point the server at itself or its private
 * network. Returns null for anything unreachable or refused.
 */
class GuardedHttp
{
    public static function get(string $url, int $timeout = 10, int $redirects = 3): ?Response
    {
        try {
            $target = OutboundUrlGuard::resolveSafeIp($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        try {
            $response = Http::timeout($timeout)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AffilStackBot/1.0)'])
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if ($response->redirect() && $redirects > 0 && ($location = $response->header('Location'))) {
            return self::get(self::absolute($url, $location), $timeout, $redirects - 1);
        }

        return $response->successful() ? $response : null;
    }

    public static function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }
}
