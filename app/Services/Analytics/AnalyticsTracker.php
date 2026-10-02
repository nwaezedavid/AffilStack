<?php

namespace App\Services\Analytics;

use App\Jobs\ResolveAnalyticsSessionGeo;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\PageView;
use App\Support\Analytics\Attribution;
use App\Support\Analytics\ConsentSettings;
use App\Support\Analytics\ConsentState;
use App\Support\Analytics\EventSanitizer;
use App\Support\Analytics\IpPrivacy;
use App\Support\Analytics\UserAgentParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Agent 04: the write side of first-party analytics, called by
 * AnalyticsBeaconController. Two very different paths:
 *
 * - Analytics consent given (checked from the afs_consent cookie on THIS
 *   request, not trusted from the page script): the page view is tied to
 *   the visitor's anonymous id, grouped into a session (30-minute
 *   inactivity / new campaign), attributed (first- and last-touch UTM,
 *   referrer, landing page, ad click id) and enriched with device, browser,
 *   OS, language and — queued — country/city.
 * - No consent: at most an anonymous aggregate count (path, traffic
 *   channel, referring site) with no cookie, no id and no user, and only
 *   if the admin left "count anonymous page views" on.
 *
 * Writes are kept to a handful of single-row INSERT/UPDATEs per page view
 * so this stays cheap on shared-hosting MariaDB.
 */
class AnalyticsTracker
{
    /**
     * Events only the server may record — a browser must never be able to
     * fake a conversion or a feature-use event.
     *
     * @var array<int, string>
     */
    public const SERVER_ONLY_EVENTS = [
        'pricing_viewed', 'plan_selected', 'checkout_started', 'signup_completed', 'purchase', 'login',
        'research_started', 'content_generated', 'campaign_sent', 'extension_used', 'page_view',
    ];

    public function __construct(protected IdentityStitcher $stitcher) {}

    /**
     * @param  array{path: string, referrer?: ?string, query?: ?string, vid?: ?string, lang?: ?string, upgrade?: ?string}  $data
     * @return array{token: ?string, vid?: string}
     */
    public function recordPageView(Request $request, array $data): array
    {
        $path = EventSanitizer::path($data['path']);
        $referrer = $data['referrer'] ?? null;
        $attribution = Attribution::resolve($referrer, $data['query'] ?? '', $request->getHost());
        $consent = ConsentState::fromRequest($request);
        $uuid = $this->visitorUuid($data['vid'] ?? null);

        if (! $consent->allowsAnalytics() || $uuid === null) {
            if (! ConsentSettings::countsAnonymousViews()) {
                return ['token' => null];
            }

            $view = PageView::create([
                'path' => $path,
                'source' => $attribution->channel,
                'referrer_host' => $attribution->referrerHost,
            ]);

            return ['token' => $view->view_token];
        }

        $now = now();
        $userId = $request->user()?->getKey();
        $result = [];

        $visitor = $this->findOrCreateVisitor($uuid, $request, $attribution, $consent, $path, $data['lang'] ?? null, $now);

        // A different account signing in on a browser already linked to
        // someone else (a shared computer): start a fresh anonymous id
        // rather than mixing two people's journeys.
        if ($userId && $visitor->user_id && $visitor->user_id !== $userId) {
            $uuid = (string) Str::uuid();
            $visitor = $this->findOrCreateVisitor($uuid, $request, $attribution, $consent, $path, $data['lang'] ?? null, $now);
            $result['vid'] = $uuid;
        }

        if ($userId && $visitor->user_id === null && $request->user()) {
            $this->stitcher->stitch($visitor, $request->user());
        }

        $session = $this->resolveSession($visitor, $request, $attribution, $consent, $path, $data['lang'] ?? null, $now);

        $attributes = [
            'path' => $path,
            'source' => $attribution->channel,
            'referrer_host' => $attribution->referrerHost,
            'user_id' => $userId,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'visitor_id' => $visitor->id,
            'analytics_session_id' => $session->id,
        ];

        // The visitor accepted analytics while on this page: upgrade the
        // anonymous count already recorded for it instead of counting the
        // same page view twice.
        $view = $this->upgradableView($data['upgrade'] ?? null, $path);

        if ($view) {
            $view->forceFill($attributes)->save();
        } else {
            $view = PageView::create($attributes);
        }

        $session->forceFill([
            'pageviews' => $session->pageviews + 1,
            'last_activity_at' => $now,
            'exit_path' => $path,
            'duration_seconds' => (int) max(0, $session->started_at->diffInSeconds($now)),
            'user_id' => $session->user_id ?? $userId,
        ])->save();

        AnalyticsVisitor::query()->whereKey($visitor->id)->update([
            'last_seen_at' => $now,
            'pageviews_count' => $visitor->pageviews_count + 1,
        ]);

        $this->recordAutomaticEvents($path, $visitor, $session, $userId, $now);

        return ['token' => $view->view_token] + $result;
    }

    /**
     * Time on page (visible seconds) and scroll depth, sent on page hide.
     */
    public function recordEngagement(string $token, int $seconds, ?int $scrollDepth): void
    {
        $view = PageView::query()->where('view_token', $token)->whereNull('duration_seconds')->first();

        if (! $view) {
            return;
        }

        $view->forceFill([
            'duration_seconds' => $seconds,
            'scroll_depth' => $view->analytics_session_id && $scrollDepth !== null ? max(0, min(100, $scrollDepth)) : null,
        ])->save();

        if ($view->analytics_session_id) {
            AnalyticsSession::query()->whereKey($view->analytics_session_id)->increment('engaged_seconds', min($seconds, 3600));
        }
    }

    /**
     * A browser event from window.affilstackTrack() or the automatic CTA /
     * outbound-link tracking. Recorded only with analytics consent.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public function recordClientEvent(Request $request, string $name, ?array $properties, ?string $path, ?string $vid): bool
    {
        if (! EventSanitizer::isValidName($name) || in_array($name, self::SERVER_ONLY_EVENTS, true)) {
            return false;
        }

        $consent = ConsentState::fromRequest($request);
        $uuid = $this->visitorUuid($vid);

        if (! $consent->allowsAnalytics() || $uuid === null) {
            return false;
        }

        $visitor = AnalyticsVisitor::query()->where('uuid', $uuid)->first();

        if (! $visitor) {
            return false;
        }

        $now = now();
        $session = $this->activeSession($visitor, $now)
            ?? $this->resolveSession($visitor, $request, Attribution::resolve(null, null, $request->getHost()), $consent, EventSanitizer::path($path), null, $now);

        $this->storeEvent($visitor, $session, $request->user()?->getKey(), $name, match ($name) {
            'cta_click', 'outbound_click' => AnalyticsEvent::CATEGORY_ENGAGEMENT,
            default => AnalyticsEvent::CATEGORY_CUSTOM,
        }, $path !== null ? EventSanitizer::path($path) : null, EventSanitizer::properties($properties), $now);

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function storeEvent(?AnalyticsVisitor $visitor, ?AnalyticsSession $session, ?int $userId, string $name, string $category, ?string $path, ?array $properties, Carbon $at, ?int $valueCents = null, ?string $currency = null, ?string $dedupeKey = null): ?AnalyticsEvent
    {
        $attributes = [
            'visitor_id' => $visitor?->id,
            'session_id' => $session?->id,
            'user_id' => $userId,
            'name' => $name,
            'category' => $category,
            'path' => $path,
            'properties' => $properties,
            'value_cents' => $valueCents,
            'currency' => $currency ? strtoupper(substr($currency, 0, 3)) : null,
            'dedupe_key' => $dedupeKey,
            'created_at' => $at,
        ];

        if ($dedupeKey !== null && AnalyticsEvent::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        try {
            $event = AnalyticsEvent::create($attributes);
        } catch (QueryException $e) {
            if ($dedupeKey !== null) {
                return null; // A concurrent duplicate won the unique index.
            }

            throw $e;
        }

        if ($session) {
            AnalyticsSession::query()->whereKey($session->id)->update([
                'events' => $session->events + 1,
                'last_activity_at' => $at,
            ]);
            $session->events++;
        }

        if ($visitor) {
            AnalyticsVisitor::query()->whereKey($visitor->id)->increment('events_count', 1, ['last_seen_at' => $at]);
        }

        return $event;
    }

    public function activeSession(AnalyticsVisitor $visitor, Carbon $now): ?AnalyticsSession
    {
        return AnalyticsSession::query()
            ->where('visitor_id', $visitor->id)
            ->where('last_activity_at', '>=', $now->copy()->subMinutes((int) config('analytics.session_timeout_minutes', 30)))
            ->orderByDesc('id')
            ->first();
    }

    protected function upgradableView(mixed $token, string $path): ?PageView
    {
        if (! is_string($token) || strlen($token) !== 48) {
            return null;
        }

        return PageView::query()
            ->where('view_token', $token)
            ->whereNull('visitor_id')
            ->where('path', $path)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->first();
    }

    protected function visitorUuid(mixed $vid): ?string
    {
        return is_string($vid) && Str::isUuid($vid) ? strtolower($vid) : null;
    }

    protected function findOrCreateVisitor(string $uuid, Request $request, Attribution $attribution, ConsentState $consent, string $path, ?string $language, Carbon $now): AnalyticsVisitor
    {
        $visitor = AnalyticsVisitor::query()->where('uuid', $uuid)->first();

        if ($visitor) {
            return $visitor;
        }

        try {
            return AnalyticsVisitor::create(array_merge(
                ['uuid' => $uuid, 'first_seen_at' => $now, 'last_seen_at' => $now],
                $this->touchAttributes('ft', $attribution, $consent, $path, $now),
                $this->touchAttributes('lt', $attribution, $consent, $path, $now),
                UserAgentParser::parse($request->userAgent()),
                ['language' => $this->language($language, $request)],
            ));
        } catch (QueryException) {
            // Two beacons from a brand-new visitor raced on the unique uuid.
            return AnalyticsVisitor::query()->where('uuid', $uuid)->firstOrFail();
        }
    }

    protected function resolveSession(AnalyticsVisitor $visitor, Request $request, Attribution $attribution, ConsentState $consent, string $path, ?string $language, Carbon $now): AnalyticsSession
    {
        $session = $this->activeSession($visitor, $now);

        $newCampaign = $session && $attribution->hasCampaign() && (
            $session->campaign !== $attribution->campaign
            || $session->source !== $attribution->source
            || $session->click_id_type !== $attribution->clickIdType
        );

        if ($session && ! $newCampaign) {
            return $session;
        }

        $ua = UserAgentParser::parse($request->userAgent());

        $session = AnalyticsSession::create([
            'visitor_id' => $visitor->id,
            'user_id' => $visitor->user_id ?? $request->user()?->getKey(),
            'started_at' => $now,
            'last_activity_at' => $now,
            'landing_path' => $path,
            'exit_path' => $path,
            'channel' => $attribution->channel,
            'source' => $attribution->source,
            'medium' => $attribution->medium,
            'campaign' => $attribution->campaign,
            'term' => $attribution->term,
            'content' => $attribution->content,
            'referrer_host' => $attribution->referrerHost,
            'click_id_type' => $attribution->clickIdType,
            'device_type' => $ua['device_type'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
            'language' => $this->language($language, $request),
            'country' => $visitor->country,
            'city' => $visitor->city,
        ]);

        $visitorUpdates = ['sessions_count' => $visitor->sessions_count + 1, 'last_seen_at' => $now];

        // Last-touch moves only for a non-direct visit (the "last non-direct
        // click" model) and never on the visitor's very first session, whose
        // ft_* and lt_* were both set when the visitor was created.
        if ($attribution->isNonDirect() && $visitor->sessions_count > 0) {
            $visitorUpdates += $this->touchAttributes('lt', $attribution, $consent, $path, $now);
        }

        AnalyticsVisitor::query()->whereKey($visitor->id)->update($visitorUpdates);
        $visitor->forceFill($visitorUpdates);

        $ip = $request->ip();
        if (IpPrivacy::isPublic($ip)) {
            ResolveAnalyticsSessionGeo::dispatch($session->id, (string) $ip);
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    protected function touchAttributes(string $prefix, Attribution $attribution, ConsentState $consent, string $path, Carbon $now): array
    {
        return [
            "{$prefix}_channel" => $attribution->channel,
            "{$prefix}_source" => $attribution->source,
            "{$prefix}_medium" => $attribution->medium,
            "{$prefix}_campaign" => $attribution->campaign,
            "{$prefix}_term" => $attribution->term,
            "{$prefix}_content" => $attribution->content,
            "{$prefix}_referrer_host" => $attribution->referrerHost,
            "{$prefix}_landing_path" => $path,
            "{$prefix}_click_id_type" => $attribution->clickIdType,
            // The click id itself is an advertising identifier — only kept
            // when the visitor also allowed advertising cookies.
            "{$prefix}_click_id" => $consent->allowsMarketing() ? $attribution->clickId : null,
            "{$prefix}_at" => $now,
        ];
    }

    protected function language(?string $language, Request $request): ?string
    {
        $language = $language ?: $request->getPreferredLanguage();

        return is_string($language) && preg_match('/^[A-Za-z]{2,3}([-_][A-Za-z0-9]{2,8})?$/', $language) === 1
            ? str_replace('_', '-', $language)
            : null;
    }

    /**
     * Pricing views and plan selections are derived from the page itself,
     * server-side, so they can't be spoofed or forgotten in a template.
     */
    protected function recordAutomaticEvents(string $path, AnalyticsVisitor $visitor, AnalyticsSession $session, ?int $userId, Carbon $now): void
    {
        if ($path === 'pricing') {
            $this->storeEvent($visitor, $session, $userId, 'pricing_viewed', AnalyticsEvent::CATEGORY_CONVERSION, $path, null, $now);

            return;
        }

        if (preg_match('#^get-started/([a-z0-9-]{1,60})$#', $path, $match) === 1 && $match[1] !== 'callback') {
            $this->storeEvent($visitor, $session, $userId, 'plan_selected', AnalyticsEvent::CATEGORY_CONVERSION, $path, ['plan' => $match[1]], $now);
        }
    }
}
