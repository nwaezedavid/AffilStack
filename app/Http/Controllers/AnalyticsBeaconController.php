<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The two endpoints the client-side beacon (partials/analytics-beacon.blade.php)
 * calls: one on page load to record a visit, one on page unload/hide to fill
 * in how long the visitor stayed. Deliberately outside every auth/admin
 * guard — it needs to fire for logged-out marketing-site visitors too — and
 * excluded from CSRF (bootstrap/app.php) the same way the payment webhooks
 * are, since a page-load beacon can't always guarantee a fresh CSRF token
 * (e.g. a page served from a browser's back/forward cache).
 */
class AnalyticsBeaconController extends Controller
{
    public function record(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:200'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'query' => ['nullable', 'string', 'max:500'],
        ]);

        if (PageView::isLikelyBot($request->userAgent())) {
            return response()->json(['token' => null]);
        }

        $path = ltrim(Str::limit(parse_url($data['path'], PHP_URL_PATH) ?? $data['path'], 200, ''), '/');
        $referrer = $data['referrer'] ?? null;
        $referrerHost = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        $source = PageView::classifySource($referrer, $data['query'] ?? '', $request->getHost());

        $view = PageView::create([
            'path' => $path === '' ? '/' : $path,
            'source' => $source,
            'referrer_host' => $referrerHost,
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
        ]);

        return response()->json(['token' => $view->view_token]);
    }

    public function duration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        // Authorized by the unguessable token handed back from record()
        // rather than by session id — a session can legitimately
        // regenerate mid-visit (e.g. the visitor logs in between page-load
        // and page-hide), which would otherwise silently drop that visit's
        // time-on-page.
        PageView::where('view_token', $data['token'])
            ->whereNull('duration_seconds')
            ->update(['duration_seconds' => $data['seconds']]);

        return response()->json(['ok' => true]);
    }
}
