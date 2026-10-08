<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The endpoints the client-side beacon (partials/analytics-beacon.blade.php
 * + public/js/afs-analytics.js) calls: one on page load to record a visit,
 * one on page unload/hide to fill in how long the visitor stayed and how
 * far they scrolled, and (agent 04) one for behaviour events. Deliberately
 * outside every auth/admin guard — it needs to fire for logged-out
 * marketing-site visitors too — and excluded from CSRF the same way the
 * payment webhooks are, since a page-load beacon can't always guarantee a
 * fresh CSRF token (e.g. a page served from a browser's back/forward
 * cache); RejectCrossSiteBeacon refuses cross-site posts instead.
 *
 * What gets stored depends on the visitor's cookie choice — see
 * AnalyticsTracker: full first-party analytics with analytics consent, an
 * anonymous aggregate count without it.
 */
class AnalyticsBeaconController extends Controller
{
    public function record(Request $request, AnalyticsTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:200'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'query' => ['nullable', 'string', 'max:500'],
            'vid' => ['nullable', 'string', 'max:40'],
            'lang' => ['nullable', 'string', 'max:20'],
            'upgrade' => ['nullable', 'string', 'size:48'],
        ]);

        if ($this->isAutomated($request)) {
            return response()->json(['token' => null]);
        }

        return response()->json($tracker->recordPageView($request, $data));
    }

    public function duration(Request $request, AnalyticsTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'scroll' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Authorized by the unguessable token handed back from record()
        // rather than by session id — a session can legitimately
        // regenerate mid-visit (e.g. the visitor logs in between page-load
        // and page-hide), which would otherwise silently drop that visit's
        // time-on-page.
        $tracker->recordEngagement($data['token'], (int) $data['seconds'], isset($data['scroll']) ? (int) $data['scroll'] : null);

        return response()->json(['ok' => true]);
    }

    public function event(Request $request, AnalyticsTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'props' => ['nullable', 'array', 'max:20'],
            'path' => ['nullable', 'string', 'max:200'],
            'vid' => ['nullable', 'string', 'max:40'],
        ]);

        if ($this->isAutomated($request)) {
            return response()->json(['ok' => false]);
        }

        $recorded = $tracker->recordClientEvent($request, $data['name'], $data['props'] ?? null, $data['path'] ?? null, $data['vid'] ?? null);

        return response()->json(['ok' => $recorded]);
    }

    /**
     * Crawlers, headless browsers and speculative prefetches are never
     * counted as visits.
     */
    protected function isAutomated(Request $request): bool
    {
        if (PageView::isLikelyBot($request->userAgent())) {
            return true;
        }

        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose')));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }
}
