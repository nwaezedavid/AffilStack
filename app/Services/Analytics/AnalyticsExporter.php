<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSegment;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\PageView;
use App\Support\Analytics\AudienceHasher;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Agent 04: the CSV downloads behind Analytics → Downloads and Audiences.
 * Each export is a generator of rows read with lazyById() (1,000 rows per
 * query), so a large export streams straight to the browser without ever
 * being held in memory — important on shared hosting.
 *
 * Audience files follow the platforms' upload specs (see AudienceHasher):
 * only people whose current choice allows marketing, every identifier
 * normalised and SHA-256 hashed — no raw email or phone ever leaves.
 */
class AnalyticsExporter
{
    /**
     * @var array<string, array{label: string, description: string, dated: bool}>
     */
    public const TYPES = [
        'events' => ['label' => 'Raw events', 'description' => 'Every behaviour event (CTA clicks, pricing views, checkouts, signups, feature use, custom events) with its properties.', 'dated' => true],
        'page_views' => ['label' => 'Page views', 'description' => 'Every recorded page view, with time on page and scroll depth. Rows without a visitor are anonymous counts from people who declined analytics.', 'dated' => true],
        'sessions' => ['label' => 'Sessions (visits)', 'description' => 'One row per visit: landing and exit page, source/medium/campaign, device, location, duration and pages viewed.', 'dated' => true],
        'visitors' => ['label' => 'Visitors', 'description' => 'One row per anonymous visitor id: first- and last-touch attribution, device and location, totals. Includes the linked user id once identified.', 'dated' => true],
        'users' => ['label' => 'Users with interests', 'description' => 'Signed-in people who allowed analytics: lifecycle stage, engagement score, features, modules, networks and products they research.', 'dated' => false],
        'consent' => ['label' => 'Consent log', 'description' => 'Proof of consent: every cookie choice with time, policy version, what was allowed and a hashed IP.', 'dated' => true],
    ];

    /**
     * @return array{0: array<int, string>, 1: Generator<int, array<int, mixed>>}
     */
    public function export(string $type, Carbon $from, Carbon $to): array
    {
        return match ($type) {
            'events' => [
                ['id', 'created_at', 'event', 'description', 'category', 'path', 'properties', 'value', 'currency', 'visitor', 'session_id', 'user_id'],
                $this->eventRows($from, $to),
            ],
            'page_views' => [
                ['id', 'created_at', 'path', 'channel', 'referrer_host', 'seconds_on_page', 'scroll_depth_pct', 'visitor', 'session_id', 'user_id'],
                $this->pageViewRows($from, $to),
            ],
            'sessions' => [
                ['id', 'started_at', 'last_activity_at', 'duration_seconds', 'engaged_seconds', 'pageviews', 'events', 'bounce', 'landing_path', 'exit_path', 'channel', 'source', 'medium', 'campaign', 'term', 'content', 'referrer_host', 'ad_click', 'device', 'browser', 'os', 'language', 'country', 'city', 'visitor', 'user_id'],
                $this->sessionRows($from, $to),
            ],
            'visitors' => [
                ['visitor', 'first_seen_at', 'last_seen_at', 'user_id', 'identified_at', 'sessions', 'pageviews', 'events',
                    'first_channel', 'first_source', 'first_medium', 'first_campaign', 'first_term', 'first_content', 'first_referrer', 'first_landing_page', 'first_ad_click', 'first_ad_click_id',
                    'last_channel', 'last_source', 'last_medium', 'last_campaign', 'last_term', 'last_content', 'last_referrer', 'last_landing_page', 'last_ad_click', 'last_ad_click_id',
                    'device', 'browser', 'os', 'language', 'country', 'city'],
                $this->visitorRows($from, $to),
            ],
            'users' => [
                ['user_id', 'name', 'email', 'lifecycle_stage', 'engagement_score', 'has_active_subscription', 'paid_at', 'first_seen_at', 'last_seen_at', 'sessions', 'pageviews', 'feature_uses_30d',
                    'top_features', 'content_modules', 'affiliate_networks', 'recent_products', 'first_channel', 'first_source', 'first_campaign', 'last_channel', 'last_source', 'last_campaign', 'country', 'device', 'marketing_consent'],
                $this->userRows(),
            ],
            'consent' => [
                ['id', 'created_at', 'consent_id', 'action', 'analytics', 'marketing', 'source', 'policy_version', 'privacy_signal', 'browser', 'device', 'page', 'user_id', 'visitor', 'ip_hash'],
                $this->consentRows($from, $to),
            ],
        };
    }

    /**
     * @return array{0: array<int, string>, 1: Generator<int, array<int, mixed>>}
     */
    public function audience(AnalyticsSegment $segment, string $platform, SegmentEvaluator $evaluator): array
    {
        $headers = $platform === 'google' ? ['Email', 'Phone'] : ['email', 'phone', 'country'];

        return [$headers, $this->audienceRows($evaluator->exportableQuery($segment), $platform)];
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function audienceRows($query, string $platform): Generator
    {
        $seen = [];

        foreach ($query->with(['user:id,email,phone,country', 'visitor:id,email_sha256,email_sha256_google,country'])->lazyById(1000, 'analytics_profiles.id', 'id') as $profile) {
            $user = $profile->user;
            $country = strtolower((string) ($user?->country ?: $profile->country ?: $profile->visitor?->country));

            if ($user) {
                $email = $platform === 'google' ? AudienceHasher::googleEmailHash($user->email) : AudienceHasher::metaEmailHash($user->email);
                $phone = $platform === 'google' ? AudienceHasher::googlePhoneHash($user->phone, $user->country) : AudienceHasher::metaPhoneHash($user->phone, $user->country);
            } else {
                $email = $platform === 'google' ? ($profile->visitor?->email_sha256_google ?: $profile->visitor?->email_sha256) : $profile->visitor?->email_sha256;
                $phone = null;
            }

            if (! $email || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            yield $platform === 'google'
                ? [$email, $phone ?? '']
                : [$email, $phone ?? '', strlen($country) === 2 ? AudienceHasher::sha256($country) : ''];
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function eventRows(Carbon $from, Carbon $to): Generator
    {
        $query = AnalyticsEvent::query()->with('visitor:id,uuid')->whereBetween('created_at', [$from, $to]);

        foreach ($query->lazyById(1000) as $event) {
            yield [
                $event->id, $event->created_at?->toDateTimeString(), $event->name, AnalyticsEvent::label($event->name), $event->category,
                $event->path, $event->properties ? json_encode($event->properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                $event->value_cents !== null ? number_format($event->value_cents / 100, 2, '.', '') : '', $event->currency,
                $event->visitor?->shortId(), $event->session_id, $event->user_id,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function pageViewRows(Carbon $from, Carbon $to): Generator
    {
        $query = PageView::query()->with('visitor:id,uuid')->whereBetween('created_at', [$from, $to]);

        foreach ($query->lazyById(1000) as $view) {
            yield [
                $view->id, $view->created_at?->toDateTimeString(), '/'.ltrim((string) $view->path, '/'), $view->source, $view->referrer_host,
                $view->duration_seconds, $view->scroll_depth, $view->visitor?->shortId(), $view->analytics_session_id, $view->user_id,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function sessionRows(Carbon $from, Carbon $to): Generator
    {
        $query = AnalyticsSession::query()->with('visitor:id,uuid')->whereBetween('started_at', [$from, $to]);

        foreach ($query->lazyById(1000) as $session) {
            yield [
                $session->id, $session->started_at?->toDateTimeString(), $session->last_activity_at?->toDateTimeString(), $session->duration_seconds, $session->engaged_seconds,
                $session->pageviews, $session->events, $session->isBounce() ? 'yes' : 'no', $session->landing_path, $session->exit_path,
                $session->channel, $session->source, $session->medium, $session->campaign, $session->term, $session->content, $session->referrer_host, $session->click_id_type,
                $session->device_type, $session->browser, $session->os, $session->language, $session->country, $session->city,
                $session->visitor?->shortId(), $session->user_id,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function visitorRows(Carbon $from, Carbon $to): Generator
    {
        $query = AnalyticsVisitor::query()->where('last_seen_at', '>=', $from)->where('first_seen_at', '<=', $to);

        foreach ($query->lazyById(1000) as $v) {
            $row = [$v->shortId(), $v->first_seen_at?->toDateTimeString(), $v->last_seen_at?->toDateTimeString(), $v->user_id, $v->identified_at?->toDateTimeString(), $v->sessions_count, $v->pageviews_count, $v->events_count];

            foreach (['ft', 'lt'] as $t) {
                array_push($row, $v->{"{$t}_channel"}, $v->{"{$t}_source"}, $v->{"{$t}_medium"}, $v->{"{$t}_campaign"}, $v->{"{$t}_term"}, $v->{"{$t}_content"}, $v->{"{$t}_referrer_host"}, $v->{"{$t}_landing_path"}, $v->{"{$t}_click_id_type"}, $v->{"{$t}_click_id"});
            }

            array_push($row, $v->device_type, $v->browser, $v->os, $v->language, $v->country, $v->city);

            yield $row;
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function userRows(): Generator
    {
        $query = AnalyticsProfile::query()->whereNotNull('user_id')->whereHas('user')->with('user:id,name,email');

        foreach ($query->lazyById(1000) as $p) {
            $interests = (array) $p->interests;

            yield [
                $p->user_id, $p->user?->name, $p->user?->email, $p->stageLabel(), $p->engagement_score, $p->has_active_subscription ? 'yes' : 'no',
                $p->paid_at?->toDateString(), $p->first_seen_at?->toDateTimeString(), $p->last_seen_at?->toDateTimeString(), $p->sessions_count, $p->pageviews_count, $p->feature_events_30d,
                $this->summarise($interests['features'] ?? [], fn ($k) => AnalyticsEvent::label($k)),
                $this->summarise($interests['modules'] ?? []),
                $this->summarise($interests['networks'] ?? []),
                implode('; ', (array) ($interests['products'] ?? [])),
                $p->ft_channel, $p->ft_source, $p->ft_campaign, $p->lt_channel, $p->lt_source, $p->lt_campaign, $p->country, $p->device_type,
                $p->marketing_consent ? 'yes' : 'no',
            ];
        }
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    protected function consentRows(Carbon $from, Carbon $to): Generator
    {
        $query = ConsentRecord::query()->with('visitor:id,uuid')->whereBetween('created_at', [$from, $to]);

        foreach ($query->lazyById(1000) as $record) {
            yield [
                $record->id, $record->created_at?->toDateTimeString(), $record->consent_id, ConsentRecord::ACTIONS[$record->action] ?? $record->action,
                $record->analytics ? 'yes' : 'no', $record->marketing ? 'yes' : 'no', $record->source, $record->policy_version, $record->gpc ? 'yes' : 'no',
                $record->browser, $record->device_type, $record->page_path, $record->user_id, $record->visitor?->shortId(), $record->ip_hash,
            ];
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    protected function summarise(array $counts, ?callable $label = null): string
    {
        return collect($counts)->map(fn ($n, $k) => ($label ? $label($k) : Str::headline((string) $k))." ({$n})")->implode('; ');
    }
}
