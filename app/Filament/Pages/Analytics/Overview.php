<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Clusters\Analytics;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use App\Models\NotFoundLog;
use App\Models\PageView;
use App\Models\Plan;
use App\Models\Subscription;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The "statistics area" requested alongside the rest of this work: traffic
 * (by period), traffic sources, popular pages, time-on-page, 404s, and paid
 * users by plan, all in one place. Built as a single custom Blade page in
 * the same style as the Documentation cluster (see its _styles partial and
 * app/Filament/Pages/Documentation/Overview.php) rather than composed from
 * Filament Table/Chart widgets — those widgets are built for real Eloquent
 * record collections with stable primary keys, and every metric here is a
 * GROUP BY aggregate with no natural per-row key, which fights that
 * machinery more than it helps. See PageView for the underlying queries.
 */
class Overview extends Page
{
    use VisibleToAnyAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $cluster = Analytics::class;

    protected static ?string $navigationLabel = 'Traffic & Stats';

    protected static ?string $title = 'Analytics';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.analytics.overview';

    public int $days = 30;

    /**
     * @return array<int, int>
     */
    public function periods(): array
    {
        return [7, 30, 90, 365];
    }

    public function setPeriod(int $days): void
    {
        $this->days = in_array($days, $this->periods(), true) ? $days : 30;
    }

    protected function since(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    public function stats(): array
    {
        $since = $this->since();
        $totalViews = PageView::where('created_at', '>=', $since)->count();
        $uniqueVisitors = PageView::uniqueVisitors($since);
        $avgDuration = PageView::averageDurationSeconds($since);
        $notFoundCount = NotFoundLog::where('last_seen_at', '>=', $since)->count();

        return [
            ['label' => 'Page views', 'value' => number_format($totalViews), 'hint' => "Last {$this->days} days"],
            ['label' => 'Unique visitors', 'value' => number_format($uniqueVisitors), 'hint' => 'By browser session'],
            ['label' => 'Avg. time on page', 'value' => $this->formatDuration($avgDuration), 'hint' => 'When measurable'],
            ['label' => '404s seen', 'value' => number_format($notFoundCount), 'hint' => 'Distinct dead links'],
        ];
    }

    protected function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) round($seconds);

        return $seconds >= 60
            ? sprintf('%dm %ds', intdiv($seconds, 60), $seconds % 60)
            : "{$seconds}s";
    }

    /**
     * Daily view counts for the period, zero-filled for days with no
     * traffic so the bar chart's x-axis stays evenly spaced.
     *
     * @return array<int, array{date: string, label: string, views: int}>
     */
    public function dailySeries(): array
    {
        $since = $this->since();
        $counts = PageView::dailyCounts($since)->keyBy('date');

        $series = [];
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $series[] = [
                'date' => $date,
                'label' => now()->subDays($i)->format($this->days > 60 ? 'M j' : 'D j'),
                'views' => (int) ($counts[$date]->views ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{path: string, views: int, avg_duration: string}>
     */
    public function popularPages(): array
    {
        return PageView::popularPaths($this->since(), 10)
            ->map(fn ($row) => [
                'path' => $row->path === '' ? '/' : '/'.$row->path,
                'views' => $row->views,
                'avg_duration' => $this->formatDuration($row->avg_duration),
            ])
            ->all();
    }

    /**
     * @return array<int, array{source: string, views: int, percent: float}>
     */
    public function trafficSources(): array
    {
        $rows = PageView::bySource($this->since());
        $total = max(1, $rows->sum('views'));

        return $rows->map(fn ($row) => [
            'source' => $row->source,
            'views' => $row->views,
            'percent' => round(($row->views / $total) * 100, 1),
        ])->all();
    }

    /**
     * @return array<int, array{plan: string, count: int, mrr: string}>
     */
    public function paidUsersByPlan(): array
    {
        // Revenue by plan is billing information — hidden from staff
        // outside that department (this page is open to every panel user).
        if (! auth()->user()?->canAccessDepartment('billing')) {
            return [];
        }

        return Subscription::query()
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.id', 'plans.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('plans.name as plan')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw("SUM(CASE WHEN billing_cycle = 'yearly' THEN plans.price_yearly_cents / 12 ELSE plans.price_monthly_cents END) as mrr_cents")
            ->get()
            ->map(fn ($row) => [
                'plan' => $row->plan,
                'count' => (int) $row->count,
                'mrr' => '$'.number_format(($row->mrr_cents ?? 0) / 100, 2),
            ])
            ->all();
    }

    public function notFoundMonitorUrl(): string
    {
        return NotFoundLogResource::getUrl('index');
    }

    public function totalPlanCount(): int
    {
        return Plan::query()->where('is_active', true)->count();
    }
}
