<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;

/**
 * Bing Webmaster Tools — API-key auth (no OAuth), raw Http calls only, same
 * reasoning as every other integration in this app (CLAUDE.md: no new
 * dependencies without approval). See BingWebmasterSetting for why this
 * stays a guided connect (paste key + verification code) rather than a
 * full auto-provisioning flow like Google's.
 */
class BingWebmasterClient
{
    protected const BASE = 'https://ssl.bing.com/webmaster/api.svc/json';

    public function __construct(protected string $apiKey) {}

    /**
     * The closest thing Bing's API has to a "ping" — lists every site
     * already registered to this account. A genuinely wrong/revoked key
     * fails outright; a correct key with the site not yet added just
     * returns a list that doesn't include it, which verifySite() reports
     * on separately.
     *
     * @return array{success: bool, message: string, sites: array<int, string>}
     */
    public function verifyApiKey(): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'message' => 'An API key is required — generate one from bing.com/webmasters under Settings > API Access.', 'sites' => []];
        }

        $response = Http::get(self::BASE.'/GetUserSites', ['apikey' => $this->apiKey]);

        if ($response->failed()) {
            return ['success' => false, 'message' => "Bing rejected this API key: {$response->body()}", 'sites' => []];
        }

        // Bing's older API wraps results in a "d" property (ASP.NET-JSON
        // convention); tolerate either shape defensively since this isn't
        // as consistently documented as Google's REST APIs.
        $sites = collect($response->json('d', $response->json()) ?: [])
            ->map(fn ($site) => is_array($site) ? ($site['Url'] ?? null) : null)
            ->filter()
            ->values()
            ->all();

        return ['success' => true, 'message' => 'API key accepted — '.count($sites).' site(s) currently registered.', 'sites' => $sites];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifySite(string $siteUrl): array
    {
        $result = $this->verifyApiKey();

        if (! $result['success']) {
            return $result;
        }

        $normalized = collect($result['sites'])->map(fn ($url) => rtrim((string) $url, '/'));

        if ($normalized->contains(rtrim($siteUrl, '/'))) {
            return ['success' => true, 'message' => "{$siteUrl} is verified and registered on Bing Webmaster Tools."];
        }

        return [
            'success' => false,
            'message' => "{$siteUrl} isn't showing up in this account's site list yet. Add it at bing.com/webmasters, choose the \"meta tag\" verification option, paste the code it gives you into the field below, then click this again once the site is live.",
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function submitSitemap(string $siteUrl, string $sitemapUrl): array
    {
        $response = Http::asJson()->post(self::BASE.'/SubmitSitemap?apikey='.urlencode($this->apiKey), [
            'siteUrl' => $siteUrl,
            'sitemapUrl' => $sitemapUrl,
        ]);

        if ($response->failed()) {
            return ['success' => false, 'message' => "Sitemap submission failed: {$response->body()}"];
        }

        return ['success' => true, 'message' => "Sitemap submitted to Bing: {$sitemapUrl}"];
    }
}
