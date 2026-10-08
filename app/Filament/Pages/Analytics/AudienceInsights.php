<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Pages\Analytics\Concerns\HasDateRange;
use App\Services\Analytics\AudienceInsights as Insights;
use App\Support\Analytics\ConsentSettings;
use App\Support\Analytics\CookieCatalog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

/**
 * Agent 04: "Build an advanced cookie system so I can understand what my
 * users want and how they use the internet … make it easier for the admin
 * to understand the data collected." One page, plain-language: traffic,
 * where it comes from, what people look at, devices and countries, the
 * visit → pricing → checkout → paid funnel, which dashboard features
 * customers use, what they research, lifecycle stages and consent rates —
 * each chart with a short "what this means" line. It also states plainly
 * what the site can and cannot see (only its own pages, never the rest of
 * someone's internet use).
 */
class AudienceInsights extends Page
{
    use HasDateRange;
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Audience Insights';

    protected static ?string $title = 'Audience Insights';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.analytics.audience-insights';

    #[Computed]
    public function insights(): Insights
    {
        [$from, $to] = $this->range();

        return new Insights($from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function disclosure(): array
    {
        return [
            'collected' => [
                'Pages viewed on this website and the dashboard, time on page and scroll depth',
                'How people arrived: search, social, referral sites, ad clicks and UTM campaigns (first and latest visit)',
                'Buttons clicked, pricing and plan views, checkouts, signups and payments',
                'Dashboard feature use: research started, content generated (by module), campaigns sent, browser extension use',
                'Device type, browser, operating system, language, and approximate country/city (from the IP address, which is then discarded)',
            ],
            'not_collected' => [
                'Anything people do on other websites, apps or search engines — a website can only see its own pages',
                'Raw IP addresses (only a salted hash in the consent log), exact location, or keystrokes',
                'Any of the above for people who refused analytics — they appear only as anonymous page-view counts',
            ],
            'categories' => collect(CookieCatalog::categories())->map(fn ($c) => count($c['cookies']))->all(),
            'retention' => ConsentSettings::retentionMonths(),
            'version' => ConsentSettings::policyVersion(),
        ];
    }

    public static function formatSeconds(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return $seconds >= 60 ? sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60) : "{$seconds}s";
    }
}
