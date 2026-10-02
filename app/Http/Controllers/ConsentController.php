<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Support\Analytics\ConsentSettings;
use App\Support\Analytics\EventSanitizer;
use App\Support\Analytics\IpPrivacy;
use App\Support\Analytics\UserAgentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Agent 04: logs every cookie choice server-side as proof of consent
 * (NDPA 2023 s.26, GDPR art. 7(1)) — the anonymous consent id from the
 * visitor's own afs_consent cookie, what they chose, against which policy
 * version, when, from which page, a salted hash of their IP, and their
 * account if they are signed in. The cookie itself is written by the
 * banner script, so this endpoint never sets anything on the browser.
 *
 * Unauthenticated and CSRF-exempt like the page-view beacon (a cached
 * marketing page can't guarantee a fresh token); cross-site posts are
 * refused by the Origin check in the beacon middleware group.
 */
class ConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consent_id' => ['required', 'uuid'],
            'analytics' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
            'action' => ['required', Rule::in(array_keys(ConsentRecord::ACTIONS))],
            'source' => ['required', Rule::in(['banner', 'preferences', 'signal', 'footer'])],
            'policy_version' => ['required', 'string', 'max:20'],
            'vid' => ['nullable', 'uuid'],
            'path' => ['nullable', 'string', 'max:500'],
            'gpc' => ['nullable', 'boolean'],
        ]);

        if (! ConsentSettings::isValidVersion($data['policy_version'])) {
            return response()->json(['ok' => false], 422);
        }

        // Linked whenever the browser sends its visitor id — including on a
        // withdrawal, which is what tells the profile builder to stop
        // profiling that (still anonymous) visitor.
        $visitorId = ! empty($data['vid'])
            ? AnalyticsVisitor::query()->where('uuid', strtolower($data['vid']))->value('id')
            : null;
        $ua = UserAgentParser::parse($request->userAgent());

        ConsentRecord::create([
            'consent_id' => strtolower($data['consent_id']),
            'visitor_id' => $visitorId,
            'user_id' => $request->user()?->getKey(),
            'analytics' => (bool) $data['analytics'],
            'marketing' => (bool) $data['marketing'],
            'action' => $data['action'],
            'source' => $data['source'],
            'policy_version' => $data['policy_version'],
            'ip_hash' => IpPrivacy::hash($request->ip()),
            'browser' => Str::limit($ua['browser'], 40, ''),
            'device_type' => $ua['device_type'],
            'page_path' => isset($data['path']) ? EventSanitizer::path($data['path']) : null,
            'gpc' => (bool) ($data['gpc'] ?? false) || $request->header('Sec-GPC') === '1',
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
