<?php

namespace App\Services\Links;

use App\Jobs\ResolveLinkClickGeo;
use App\Models\LinkClick;
use App\Models\Offer;
use App\Models\TrackedLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Feature 1: branded link cloaking & click analytics. Every generated piece
 * of content that carries the "{{AFFILIATE_LINK}}" placeholder (see
 * Offer::cloak()) gets routed through a short /go/{code} link instead of the
 * bare product URL, so every click is measurable — the same tracking engine
 * feature 2 (conversion & earnings tracker) and feature 12 (referral
 * program) are built to reuse.
 */
class LinkCloakingService
{
    /**
     * One link per (offer, content channel) — regenerating that channel's
     * content reuses the existing link rather than fragmenting click history
     * across a new one every time.
     */
    public function getOrCreateForOffer(Offer $offer, string $module = 'general'): TrackedLink
    {
        return TrackedLink::firstOrCreate(
            ['offer_id' => $offer->id, 'module' => $module],
            [
                'user_id' => $offer->user_id,
                'destination_url' => $offer->product_url,
                'code' => $this->generateUniqueCode(),
            ],
        );
    }

    /**
     * Called from the public, unauthenticated /go/{code} redirect. Logging a
     * click must never slow down or break the redirect: the geolocation
     * lookup — the only part of this that talks to an external service —
     * happens afterward, in the background.
     */
    public function recordClick(TrackedLink $link, Request $request): LinkClick
    {
        // Clamped: header sizes are attacker-chosen on this public route.
        $userAgent = Str::limit((string) $request->userAgent(), 500, '');

        $click = $link->clicks()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'device_type' => $this->classifyDevice($userAgent),
            'browser' => $this->classifyBrowser($userAgent),
            'referrer' => Str::limit((string) $request->header('referer'), 1000, '') ?: null,
            'clicked_at' => now(),
        ]);

        $link->increment('clicks_count');

        ResolveLinkClickGeo::dispatch($click);

        return $click;
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(7));
        } while (TrackedLink::where('code', $code)->exists());

        return $code;
    }

    /**
     * Deliberately simple user-agent sniffing — good enough for a "mobile vs
     * desktop vs tablet" analytics breakdown, not meant to be a full device
     * detection library.
     */
    protected function classifyDevice(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'unknown';
        }

        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent)) {
            return 'bot';
        }

        if (preg_match('/ipad|tablet(?!.*mobile)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android.*mobile/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function classifyBrowser(string $userAgent): string
    {
        return match (true) {
            $userAgent === '' => 'unknown',
            (bool) preg_match('/edg\//i', $userAgent) => 'Edge',
            (bool) preg_match('/chrome|crios/i', $userAgent) => 'Chrome',
            (bool) preg_match('/firefox|fxios/i', $userAgent) => 'Firefox',
            (bool) preg_match('/safari/i', $userAgent) => 'Safari',
            default => 'Other',
        };
    }
}
