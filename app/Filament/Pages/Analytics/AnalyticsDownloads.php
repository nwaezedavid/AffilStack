<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Pages\Analytics\Concerns\HasDateRange;
use App\Models\AnalyticsSegment;
use App\Services\Analytics\AnalyticsExporter;
use App\Services\Analytics\SegmentEvaluator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Agent 04: "I should be able to download these data from the admin
 * dashboard area." Every analytics dataset as a streamed CSV for a date
 * range, plus the ad-audience files. Downloads are restricted to full
 * admins (they contain personal data) and each one is audit-logged; the
 * most recent downloads are listed here.
 */
class AnalyticsDownloads extends Page
{
    use HasDateRange;
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?string $title = 'Download analytics data';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.analytics.downloads';

    public function canDownload(): bool
    {
        return (bool) auth()->user()?->isFullAdmin();
    }

    public function exportUrl(string $type): string
    {
        [$from, $to] = $this->range();

        return route('admin.analytics.export', ['type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    /**
     * @return array<string, array{label: string, description: string, dated: bool}>
     */
    public function types(): array
    {
        return AnalyticsExporter::TYPES;
    }

    /**
     * @return Collection<int, AnalyticsSegment>
     */
    public function segments(): Collection
    {
        SegmentEvaluator::ensurePresets();

        return AnalyticsSegment::query()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function recentDownloads(): Collection
    {
        return Activity::query()->where('log_name', 'analytics-export')->with('causer')->latest('id')->limit(10)->get();
    }
}
