<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSession;
use App\Models\ConsentRecord;
use App\Models\PageView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agent 04: the read side behind Analytics → Audience Insights. Every
 * method is a handful of GROUP BY queries over indexed columns for one
 * date range, returning plain arrays the Blade view turns into charts.
 * Visit-level numbers (sessions, devices, sources) only cover people who
 * allowed analytics; page-view totals also include the anonymous counts.
 */
class AudienceInsights
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    /**
     * @return array<string, int|float|null>
     */
    public function kpis(): array
    {
        $sessions = $this->sessions();
        $sessionCount = (clone $sessions)->count();
        $bounces = (clone $sessions)->where('pageviews', '<=', 1)->where('events', 0)->where('engaged_seconds', '<', 10)->count();
        $pageViews = PageView::query()->whereBetween('created_at', [$this->from, $this->to]);
        $totalViews = (clone $pageViews)->count();
        $anonymousViews = (clone $pageViews)->whereNull('visitor_id')->count();

        return [
            'visitors' => (clone $sessions)->distinct()->count('visitor_id'),
            'new_visitors' => DB::table('analytics_visitors')->whereBetween('first_seen_at', [$this->from, $this->to])->count(),
            'sessions' => $sessionCount,
            'page_views' => $totalViews,
            'anonymous_share' => $totalViews > 0 ? round($anonymousViews / $totalViews * 100, 1) : null,
            'avg_engaged_seconds' => $sessionCount > 0 ? (int) round((clone $sessions)->avg('engaged_seconds')) : null,
            'pages_per_session' => $sessionCount > 0 ? round((clone $sessions)->avg('pageviews'), 1) : null,
            'bounce_rate' => $sessionCount > 0 ? round($bounces / $sessionCount * 100, 1) : null,
            'signups' => $this->events()->where('name', 'signup_completed')->count(),
            'revenue_events' => $this->events()->where('name', 'purchase')->count(),
        ];
    }

    /**
     * Daily page views (all), sessions and visitors, zero-filled.
     *
     * @return array<int, array{date: string, label: string, views: int, sessions: int, visitors: int}>
     */
    public function trafficSeries(): array
    {
        $views = PageView::query()->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');
        $sessions = $this->sessions()->selectRaw('DATE(started_at) as day, COUNT(*) as total, COUNT(DISTINCT visitor_id) as visitors')->groupBy('day')->get()->keyBy('day');

        $series = [];
        $days = (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
        $long = $days > 62;

        for ($date = $this->from->copy()->startOfDay(); $date->lessThanOrEqualTo($this->to); $date->addDay()) {
            $key = $date->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $date->format($long ? 'M j' : 'D j'),
                'views' => (int) ($views[$key] ?? 0),
                'sessions' => (int) ($sessions[$key]->total ?? 0),
                'visitors' => (int) ($sessions[$key]->visitors ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    public function channels(): array
    {
        return $this->share($this->sessions()->select('channel as label', DB::raw('COUNT(*) as value'))->groupBy('channel')->orderByDesc('value')->get(), 'Unknown');
    }

    /**
     * @return array<int, array{source: string, medium: string, campaign: ?string, sessions: int, signups: int}>
     */
    public function sourceMediums(int $limit = 10): array
    {
        $rows = $this->sessions()
            ->select('source', 'medium', 'campaign', DB::raw('COUNT(*) as sessions'))
            ->groupBy('source', 'medium', 'campaign')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'source' => $row->source ?: '(not set)',
            'medium' => $row->medium ?: '(not set)',
            'campaign' => $row->campaign,
            'sessions' => (int) $row->sessions,
        ])->all();
    }

    /**
     * @return array<int, array{path: string, sessions: int, bounce_rate: float, pages: float}>
     */
    public function landingPages(int $limit = 10): array
    {
        return $this->sessions()
            ->select('landing_path')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('SUM(CASE WHEN pageviews <= 1 AND events = 0 AND engaged_seconds < 10 THEN 1 ELSE 0 END) as bounces')
            ->selectRaw('AVG(pageviews) as pages')
            ->groupBy('landing_path')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $this->displayPath($row->landing_path),
                'sessions' => (int) $row->sessions,
                'bounce_rate' => $row->sessions > 0 ? round($row->bounces / $row->sessions * 100) : 0,
                'pages' => round((float) $row->pages, 1),
            ])->all();
    }

    /**
     * @return array<int, array{path: string, views: int, avg_seconds: ?int, avg_scroll: ?int}>
     */
    public function topPages(int $limit = 10): array
    {
        return PageView::query()->whereBetween('created_at', [$this->from, $this->to])
            ->select('path')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('AVG(duration_seconds) as avg_seconds')
            ->selectRaw('AVG(scroll_depth) as avg_scroll')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $this->displayPath($row->path),
                'views' => (int) $row->views,
                'avg_seconds' => $row->avg_seconds !== null ? (int) round($row->avg_seconds) : null,
                'avg_scroll' => $row->avg_scroll !== null ? (int) round($row->avg_scroll) : null,
            ])->all();
    }

    /**
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    public function breakdown(string $column, int $limit = 8): array
    {
        abort_unless(in_array($column, ['device_type', 'browser', 'os', 'country', 'language'], true), 400);

        return $this->share($this->sessions()->select("{$column} as label", DB::raw('COUNT(*) as value'))->groupBy($column)->orderByDesc('value')->limit($limit)->get(), 'Unknown');
    }

    /**
     * Distinct people at each step — visited, viewed pricing, started
     * checkout, paid — plus anonymous event counts for people who declined
     * analytics (counted, but not as people).
     *
     * @return array{steps: array<int, array{label: string, value: int, rate_from_previous: ?float, rate_from_start: ?float}>, anonymous: array<string, int>}
     */
    public function funnel(): array
    {
        $visited = $this->sessions()->distinct()->count('visitor_id');
        $steps = [['label' => 'Visited the site', 'value' => $visited]];

        foreach (['pricing_viewed' => 'Viewed pricing', 'checkout_started' => 'Started checkout', 'signup_completed' => 'Paid & signed up'] as $event => $label) {
            $steps[] = ['label' => $label, 'value' => $this->distinctPeople($event)];
        }

        $previous = null;
        foreach ($steps as $i => $step) {
            $steps[$i]['rate_from_previous'] = $previous ? round($step['value'] / $previous * 100, 1) : null;
            $steps[$i]['rate_from_start'] = $visited > 0 && $i > 0 ? round($step['value'] / $visited * 100, 1) : null;
            $previous = $step['value'] ?: null;
        }

        return [
            'steps' => $steps,
            'anonymous' => [
                'checkout_started' => $this->events()->where('name', 'checkout_started')->whereNull('user_id')->whereNull('visitor_id')->count(),
                'signup_completed' => $this->events()->where('name', 'signup_completed')->whereNull('user_id')->whereNull('visitor_id')->count(),
            ],
        ];
    }

    /**
     * Dashboard feature use (all events, anonymous counts included) with
     * content generation split by module.
     *
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    public function topFeatures(): array
    {
        $features = (array) config('analytics.feature_events');
        $rows = $this->events()->whereIn('name', array_diff($features, ['content_generated']))
            ->select('name', DB::raw('COUNT(*) as value'))->groupBy('name')->get()
            ->map(fn ($row) => (object) ['label' => AnalyticsEvent::label($row->name), 'value' => (int) $row->value]);

        $modules = $this->events()->where('name', 'content_generated')
            ->select('properties->module as module', DB::raw('COUNT(*) as value'))
            ->groupBy('properties->module')->get()
            ->map(fn ($row) => (object) ['label' => 'Generated: '.str($row->module ?: 'other')->replace('_', ' ')->title(), 'value' => (int) $row->value]);

        return $this->share($rows->concat($modules)->sortByDesc('value')->take(10)->values(), 'Other');
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    public function events(): Builder
    {
        return AnalyticsEvent::query()->whereBetween('created_at', [$this->from, $this->to]);
    }

    /**
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    public function eventTotals(int $limit = 12): array
    {
        return $this->share(
            $this->events()->select('name', DB::raw('COUNT(*) as value'))->groupBy('name')->orderByDesc('value')->limit($limit)->get()
                ->map(fn ($row) => (object) ['label' => AnalyticsEvent::label($row->name), 'value' => (int) $row->value]),
            'Other'
        );
    }

    /**
     * Lifecycle stage of everyone with a profile (current, not date-bound).
     *
     * @return array<int, array{label: string, value: int, percent: float, key: string}>
     */
    public function lifecycle(): array
    {
        $counts = AnalyticsProfile::query()->select('lifecycle_stage', DB::raw('COUNT(*) as value'))->groupBy('lifecycle_stage')->pluck('value', 'lifecycle_stage');
        $total = max(1, $counts->sum());

        return collect(AnalyticsProfile::STAGES)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'value' => (int) ($counts[$key] ?? 0),
            'percent' => round(($counts[$key] ?? 0) / $total * 100, 1),
        ])->values()->all();
    }

    /**
     * What signed-in users research and create, summed across profiles.
     *
     * @return array{networks: array<int, array{label: string, value: int, percent: float}>, modules: array<int, array{label: string, value: int, percent: float}>, products: array<int, array{label: string, value: int, percent: float}>}
     */
    public function interests(): array
    {
        $networks = [];
        $modules = [];
        $products = [];

        AnalyticsProfile::query()->whereNotNull('user_id')->whereNotNull('interests')->select('id', 'interests')
            ->lazyById(500)->each(function (AnalyticsProfile $profile) use (&$networks, &$modules, &$products): void {
                $interests = (array) $profile->interests;
                foreach ((array) ($interests['networks'] ?? []) as $name => $n) {
                    $networks[$name] = ($networks[$name] ?? 0) + 1;
                }
                foreach ((array) ($interests['modules'] ?? []) as $name => $n) {
                    $modules[$name] = ($modules[$name] ?? 0) + (int) $n;
                }
                foreach ((array) ($interests['products'] ?? []) as $name) {
                    $key = mb_strtolower(trim((string) $name));
                    if ($key !== '') {
                        $products[$key] = ($products[$key] ?? 0) + 1;
                    }
                }
            });

        $toRows = fn (array $counts, bool $headline = false) => $this->share(collect($counts)->sortDesc()->take(8)->map(fn ($v, $k) => (object) ['label' => $headline ? str((string) $k)->replace('_', ' ')->title()->toString() : (string) $k, 'value' => $v])->values(), 'Other');

        return [
            'networks' => $toRows($networks),
            'modules' => $toRows($modules, true),
            'products' => $toRows(collect($products)->mapWithKeys(fn ($v, $k) => [mb_convert_case($k, MB_CASE_TITLE) => $v])->all()),
        ];
    }

    /**
     * Opt-in rates from each browser's latest choice made in the range.
     *
     * @return array{total: int, analytics_rate: ?float, marketing_rate: ?float, actions: array<int, array{label: string, value: int, percent: float}>, signals: int}
     */
    public function consent(): array
    {
        $latest = ConsentRecord::query()->whereBetween('created_at', [$this->from, $this->to])->selectRaw('MAX(id)')->groupBy('consent_id');
        $records = ConsentRecord::query()->whereIn('id', $latest);
        $total = (clone $records)->count();

        return [
            'total' => $total,
            'analytics_rate' => $total > 0 ? round((clone $records)->where('analytics', true)->count() / $total * 100, 1) : null,
            'marketing_rate' => $total > 0 ? round((clone $records)->where('marketing', true)->count() / $total * 100, 1) : null,
            'signals' => (clone $records)->where('action', 'signal')->count(),
            'actions' => $this->share(
                (clone $records)->select('action', DB::raw('COUNT(*) as value'))->groupBy('action')->orderByDesc('value')->get()
                    ->map(fn ($row) => (object) ['label' => ConsentRecord::ACTIONS[$row->action] ?? $row->action, 'value' => (int) $row->value]),
                'Other'
            ),
        ];
    }

    /**
     * @return Builder<AnalyticsSession>
     */
    protected function sessions(): Builder
    {
        return AnalyticsSession::query()->whereBetween('started_at', [$this->from, $this->to]);
    }

    protected function distinctPeople(string $event): int
    {
        $base = $this->events()->where('name', $event);

        return (clone $base)->whereNotNull('user_id')->distinct()->count('user_id')
            + (clone $base)->whereNull('user_id')->whereNotNull('visitor_id')->distinct()->count('visitor_id');
    }

    /**
     * @param  iterable<int, object{label: ?string, value: int|string}>  $rows
     * @return array<int, array{label: string, value: int, percent: float}>
     */
    protected function share(iterable $rows, string $fallback): array
    {
        $rows = collect($rows);
        $total = max(1, $rows->sum(fn ($row) => (int) $row->value));

        return $rows->map(fn ($row) => [
            'label' => (string) ($row->label ?: $fallback),
            'value' => (int) $row->value,
            'percent' => round(((int) $row->value) / $total * 100, 1),
        ])->values()->all();
    }

    protected function displayPath(?string $path): string
    {
        $path = (string) $path;

        return $path === '' || $path === '/' ? '/' : '/'.ltrim($path, '/');
    }
}
