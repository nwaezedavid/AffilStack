<?php

namespace App\Services\Leads;

use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Reads the public contact emails a business publishes on its own website
 * (home, contact and about pages). Every request is checked against
 * OutboundUrlGuard and pinned to the vetted IP, so a website address can
 * never point the server at itself or its private network.
 */
class WebsiteEmailFinder
{
    protected const MAX_BYTES = 600_000;

    /**
     * @return array<int, string> emails, ones on the website's own domain first
     */
    public function find(string $website): array
    {
        $base = $this->normalise($website);

        if ($base === null) {
            return [];
        }

        $domain = LeadEmail::domainOfUrl($base);
        $emails = [];

        foreach ((array) config('leads.website_pages', ['']) as $path) {
            $html = $this->fetch($base.$path);

            if ($html === null) {
                continue;
            }

            $emails = array_merge($emails, $this->extract($html));

            // The home page alone is usually enough once it lists an address on the site's domain.
            if (collect($emails)->contains(fn (string $email): bool => LeadEmail::domainOf($email) === $domain)) {
                break;
            }
        }

        return collect($emails)
            ->unique()
            ->sortBy(fn (string $email): int => LeadEmail::domainOf($email) === $domain ? 0 : 1)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function extract(string $html): array
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $text = str_ireplace(['mailto:', '%40'], [' ', '@'], $text);
        $text = preg_replace('/\s*(\[at\]|\(at\))\s*/i', '@', $text) ?? $text;

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,24}/i', $text, $matches);

        return collect($matches[0])
            ->map(fn (string $email): string => strtolower(trim($email, '.')))
            ->filter(fn (string $email): bool => LeadEmail::isUsable($email))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalise(string $website): ?string
    {
        $website = trim($website);

        if ($website === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        $parts = parse_url($website);

        if (empty($parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    protected function fetch(string $url, int $redirects = 2): ?string
    {
        try {
            $target = OutboundUrlGuard::resolveSafeIp($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('leads.website_fetch_timeout', 8))
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AffilStackBot/1.0; +'.url('/').')', 'Accept' => 'text/html'])
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if ($response->redirect() && $redirects > 0 && ($location = $response->header('Location'))) {
            $next = str_starts_with($location, 'http') ? $location : rtrim($url, '/').'/'.ltrim($location, '/');

            return $this->fetch($next, $redirects - 1);
        }

        if (! $response->successful()) {
            return null;
        }

        return substr($response->body(), 0, self::MAX_BYTES);
    }
}
