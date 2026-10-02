<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\PageView;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agent 04: builds the interest & intent profile for each person who
 * allowed analytics — signed-in users (merging every device/visitor linked
 * to them) and still-anonymous visitors. A profile answers "what does this
 * person want and how engaged are they": features they use, products they
 * research and affiliate networks they pick, content modules they generate,
 * an engagement score (0–100) and a lifecycle stage:
 *
 *   visitor → engaged → pricing_viewer → checkout_started → customer
 *   (new) → active / at_risk → churned
 *
 * Run by `analytics:build-profiles` (incremental every 30 minutes, full
 * nightly). People whose latest choice refuses analytics get no profile —
 * an existing one is deleted the next time the builder sees them.
 */
class AnalyticsProfileBuilder
{
    /**
     * @return array{users: int, visitors: int, removed: int}
     */
    public function rebuild(?Carbon $since = null): array
    {
        $counts = ['users' => 0, 'visitors' => 0, 'removed' => 0];

        $counts['removed'] += $this->removeWithdrawn();

        $this->consentedUserIds($since)->chunk(500)->each(function (Collection $ids) use (&$counts): void {
            User::query()->whereIn('id', $ids)->get()->each(function (User $user) use (&$counts): void {
                if ($this->buildForUser($user)) {
                    $counts['users']++;
                }
            });
        });

        AnalyticsVisitor::query()
            ->whereNull('user_id')
            ->when($since, fn ($query) => $query->where('last_seen_at', '>=', $since))
            ->chunkById(500, function (Collection $visitors) use (&$counts): void {
                $visitors->each(function (AnalyticsVisitor $visitor) use (&$counts): void {
                    if ($this->buildForVisitor($visitor)) {
                        $counts['visitors']++;
                    }
                });
            });

        return $counts;
    }

    public function buildForUser(User $user): ?AnalyticsProfile
    {
        $consent = ConsentRecord::latestForUser($user->id);

        if (! $consent || ! $consent->analytics) {
            AnalyticsProfile::query()->where('user_id', $user->id)->delete();

            return null;
        }

        $windowStart = now()->subDays((int) config('analytics.engagement.window_days', 30));
        $visitors = AnalyticsVisitor::query()->where('user_id', $user->id)->get();
        $firstVisitor = $visitors->sortBy('first_seen_at')->first();
        $lastTouch = $visitors->filter(fn ($v) => $v->lt_at)->sortByDesc('lt_at')->first() ?? $firstVisitor;

        $sessions = AnalyticsSession::query()->where('user_id', $user->id);
        $recentSessions = (clone $sessions)->where('started_at', '>=', $windowStart)->get(['started_at', 'engaged_seconds']);
        $eventStats = $this->eventStats(AnalyticsEvent::query()->where('user_id', $user->id), $windowStart);

        $active = Subscription::query()->where('user_id', $user->id)->where('status', 'active')->exists();
        $everSubscribed = Subscription::query()->where('user_id', $user->id)->exists();
        $paidAt = PaymentTransaction::query()->where('user_id', $user->id)->where('status', 'successful')->where('amount_cents', '>', 0)->min('processed_at')
            ?? Subscription::query()->where('user_id', $user->id)->min('created_at');
        $paidAt = $paidAt ? Carbon::parse($paidAt) : null;

        $lastSeen = collect([$user->last_active_at, $visitors->max('last_seen_at')])->filter()->max();
        $score = $this->engagementScore($recentSessions, PageView::query()->where('user_id', $user->id)->where('created_at', '>=', $windowStart)->count(), $eventStats['feature_30d']);

        $stage = $this->customerStage($active, $everSubscribed, $paidAt, $lastSeen ? Carbon::parse($lastSeen) : null)
            ?? $this->prePurchaseStage($eventStats, $score, (clone $sessions)->count());

        return AnalyticsProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'visitor_id' => null,
            'lifecycle_stage' => $stage,
            'engagement_score' => $score,
            'first_seen_at' => $firstVisitor?->first_seen_at ?? $user->created_at,
            'last_seen_at' => $lastSeen,
            'sessions_count' => (clone $sessions)->count(),
            'pageviews_count' => PageView::query()->where('user_id', $user->id)->count(),
            'feature_events_30d' => $eventStats['feature_30d'],
            'viewed_pricing_at' => $eventStats['last']['pricing_viewed'] ?? null,
            'checkout_started_at' => $eventStats['last']['checkout_started'] ?? null,
            'paid_at' => $paidAt,
            'has_active_subscription' => $active,
            'country' => $user->country ?: $visitors->pluck('country')->filter()->first(),
            'device_type' => $lastTouch?->device_type,
            'ft_channel' => $firstVisitor?->ft_channel,
            'ft_source' => $firstVisitor?->ft_source,
            'ft_campaign' => $firstVisitor?->ft_campaign,
            'lt_channel' => $lastTouch?->lt_channel,
            'lt_source' => $lastTouch?->lt_source,
            'lt_campaign' => $lastTouch?->lt_campaign,
            'interests' => $this->userInterests($user, $eventStats),
            'marketing_consent' => (bool) $consent->marketing,
            'computed_at' => now(),
        ]);
    }

    public function buildForVisitor(AnalyticsVisitor $visitor): ?AnalyticsProfile
    {
        if ($visitor->user_id !== null) {
            AnalyticsProfile::query()->where('visitor_id', $visitor->id)->delete();

            return null;
        }

        $consent = ConsentRecord::query()->where('visitor_id', $visitor->id)->orderByDesc('id')->first();

        // A visitor only exists because analytics was allowed when it was
        // created; a later refusal from the same browser stops profiling.
        if ($consent && ! $consent->analytics) {
            AnalyticsProfile::query()->where('visitor_id', $visitor->id)->delete();

            return null;
        }

        $windowStart = now()->subDays((int) config('analytics.engagement.window_days', 30));
        $recentSessions = AnalyticsSession::query()->where('visitor_id', $visitor->id)->where('started_at', '>=', $windowStart)->get(['started_at', 'engaged_seconds']);
        $eventStats = $this->eventStats(AnalyticsEvent::query()->where('visitor_id', $visitor->id), $windowStart);
        $score = $this->engagementScore($recentSessions, PageView::query()->where('visitor_id', $visitor->id)->where('created_at', '>=', $windowStart)->count(), $eventStats['feature_30d']);

        return AnalyticsProfile::query()->updateOrCreate(['visitor_id' => $visitor->id], [
            'user_id' => null,
            'lifecycle_stage' => $this->prePurchaseStage($eventStats, $score, (int) $visitor->sessions_count),
            'engagement_score' => $score,
            'first_seen_at' => $visitor->first_seen_at,
            'last_seen_at' => $visitor->last_seen_at,
            'sessions_count' => (int) $visitor->sessions_count,
            'pageviews_count' => (int) $visitor->pageviews_count,
            'feature_events_30d' => $eventStats['feature_30d'],
            'viewed_pricing_at' => $eventStats['last']['pricing_viewed'] ?? null,
            'checkout_started_at' => $eventStats['last']['checkout_started'] ?? null,
            'paid_at' => null,
            'has_active_subscription' => false,
            'country' => $visitor->country,
            'device_type' => $visitor->device_type,
            'ft_channel' => $visitor->ft_channel,
            'ft_source' => $visitor->ft_source,
            'ft_campaign' => $visitor->ft_campaign,
            'lt_channel' => $visitor->lt_channel,
            'lt_source' => $visitor->lt_source,
            'lt_campaign' => $visitor->lt_campaign,
            'interests' => $this->visitorInterests($visitor),
            'marketing_consent' => (bool) $consent?->marketing,
            'computed_at' => now(),
        ]);
    }

    /**
     * The 0–100 engagement score over the last 30 days: active days,
     * page views, dashboard feature use and engaged time, each weighted and
     * capped (config/analytics.php "engagement").
     *
     * @param  Collection<int, AnalyticsSession>  $recentSessions
     */
    public function engagementScore(Collection $recentSessions, int $recentPageViews, int $recentFeatureEvents): int
    {
        $c = config('analytics.engagement');
        $activeDays = $recentSessions->map(fn ($s) => Carbon::parse($s->started_at)->toDateString())->unique()->count();
        $engagedMinutes = $recentSessions->sum('engaged_seconds') / 60;

        $score = min($c['active_day_cap'], $activeDays * $c['active_day_points'])
            + min($c['page_view_cap'], $recentPageViews * $c['page_view_points'])
            + min($c['feature_event_cap'], $recentFeatureEvents * $c['feature_event_points'])
            + min($c['engaged_minute_cap'], $engagedMinutes * $c['engaged_minute_points']);

        return (int) max(0, min(100, round($score)));
    }

    protected function customerStage(bool $active, bool $everSubscribed, ?Carbon $paidAt, ?Carbon $lastSeen): ?string
    {
        $c = config('analytics.lifecycle');

        if ($active) {
            if ($paidAt && $paidAt->greaterThanOrEqualTo(now()->subDays($c['new_customer_days']))) {
                return 'customer';
            }

            return $lastSeen && $lastSeen->greaterThanOrEqualTo(now()->subDays($c['active_within_days'])) ? 'active' : 'at_risk';
        }

        return $everSubscribed ? 'churned' : null;
    }

    /**
     * @param  array{counts: array<string, int>, last: array<string, Carbon>, feature_30d: int}  $eventStats
     */
    protected function prePurchaseStage(array $eventStats, int $score, int $sessions): string
    {
        $c = config('analytics.lifecycle');

        return match (true) {
            isset($eventStats['counts']['checkout_started']) => 'checkout_started',
            isset($eventStats['counts']['pricing_viewed']) || isset($eventStats['counts']['plan_selected']) => 'pricing_viewer',
            $score >= $c['engaged_score'] || $sessions >= $c['engaged_sessions'] => 'engaged',
            default => 'visitor',
        };
    }

    /**
     * @return array{counts: array<string, int>, last: array<string, Carbon>, feature_30d: int}
     */
    protected function eventStats($query, Carbon $windowStart): array
    {
        $rows = (clone $query)
            ->select('name', DB::raw('COUNT(*) as total'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('name')
            ->get();

        return [
            'counts' => $rows->mapWithKeys(fn ($row) => [$row->name => (int) $row->total])->all(),
            'last' => $rows->mapWithKeys(fn ($row) => [$row->name => Carbon::parse($row->last_at)])->all(),
            'feature_30d' => (clone $query)->where('category', AnalyticsEvent::CATEGORY_FEATURE)->where('created_at', '>=', $windowStart)->count(),
        ];
    }

    /**
     * @param  array{counts: array<string, int>}  $eventStats
     * @return array<string, mixed>
     */
    protected function userInterests(User $user, array $eventStats): array
    {
        $features = array_intersect_key($eventStats['counts'], array_flip((array) config('analytics.feature_events')));
        arsort($features);

        $modules = Generation::query()->where('user_id', $user->id)->where('status', 'completed')->where('module', '!=', 'research')
            ->select('module', DB::raw('COUNT(*) as total'))->groupBy('module')->orderByDesc('total')->limit(8)
            ->pluck('total', 'module')->map(fn ($n) => (int) $n)->all();

        $networks = Offer::query()->where('user_id', $user->id)->where('is_sandbox', false)->whereNotNull('affiliate_network')->where('affiliate_network', '!=', '')
            ->select('affiliate_network', DB::raw('COUNT(*) as total'))->groupBy('affiliate_network')->orderByDesc('total')->limit(8)
            ->pluck('total', 'affiliate_network')->map(fn ($n) => (int) $n)->all();

        $products = Offer::query()->where('user_id', $user->id)->where('is_sandbox', false)->latest('id')->limit(5)->pluck('product_name')->filter()->values()->all();

        return array_filter([
            'features' => $features,
            'modules' => $modules,
            'networks' => $networks,
            'products' => $products,
            'ctas' => $this->topCtas(AnalyticsEvent::query()->where('user_id', $user->id)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function visitorInterests(AnalyticsVisitor $visitor): array
    {
        $pages = PageView::query()->where('visitor_id', $visitor->id)
            ->select('path', DB::raw('COUNT(*) as total'))->groupBy('path')->orderByDesc('total')->limit(6)
            ->pluck('total', 'path')->map(fn ($n) => (int) $n)->all();

        return array_filter([
            'top_pages' => $pages,
            'ctas' => $this->topCtas(AnalyticsEvent::query()->where('visitor_id', $visitor->id)),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function topCtas($query): array
    {
        return (clone $query)->where('name', 'cta_click')->latest('id')->limit(200)->pluck('properties')
            ->map(fn ($props) => is_array($props) ? ($props['label'] ?? null) : null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->all();
    }

    /**
     * Users whose latest consent record allows analytics (optionally only
     * those with any activity since $since).
     *
     * @return Collection<int, int>
     */
    protected function consentedUserIds(?Carbon $since): Collection
    {
        $latest = ConsentRecord::query()->whereNotNull('user_id')->selectRaw('MAX(id)')->groupBy('user_id');

        return ConsentRecord::query()
            ->whereIn('id', $latest)
            ->where('analytics', true)
            ->when($since, function ($query) use ($since) {
                $query->where(function ($q) use ($since) {
                    $q->where('created_at', '>=', $since)
                        ->orWhereIn('user_id', User::query()->where('last_active_at', '>=', $since)->select('id'))
                        ->orWhereIn('user_id', AnalyticsVisitor::query()->where('last_seen_at', '>=', $since)->whereNotNull('user_id')->select('user_id'))
                        ->orWhereIn('user_id', PaymentTransaction::query()->where('updated_at', '>=', $since)->whereNotNull('user_id')->select('user_id'))
                        ->orWhereIn('user_id', Subscription::query()->where('updated_at', '>=', $since)->select('user_id'));
                });
            })
            ->pluck('user_id');
    }

    /**
     * Drops profiles of users whose latest choice no longer allows analytics.
     */
    protected function removeWithdrawn(): int
    {
        $latest = ConsentRecord::query()->whereNotNull('user_id')->selectRaw('MAX(id)')->groupBy('user_id');
        $withdrawn = ConsentRecord::query()->whereIn('id', $latest)->where('analytics', false)->select('user_id');

        return AnalyticsProfile::query()->whereIn('user_id', $withdrawn)->delete()
            + AnalyticsProfile::query()->whereNotNull('user_id')->whereNotIn('user_id', ConsentRecord::query()->whereNotNull('user_id')->select('user_id'))->delete();
    }
}
