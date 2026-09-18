<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Services\Analytics\GoogleSiteAnalyticsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * The Google Site Kit-equivalent auto-connect page: one "Connect with
 * Google" button (reusing the Site > Google Login OAuth client), then three
 * individually re-runnable setup steps for GA4, Search Console, and Tag
 * Manager — see GoogleSiteAnalyticsService for why these are separate
 * actions rather than one all-or-nothing setup. No form here (nothing to
 * type in) — same purely action-driven shape as ConnectionsHealth.
 */
class GoogleSiteAnalyticsSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Site Analytics';

    protected static ?string $title = 'Site Analytics';

    protected string $view = 'filament.pages.google-site-analytics-settings';

    /**
     * @var array<int, array{name: string, displayName: string}>
     */
    public array $analyticsAccounts = [];

    /**
     * @var array<int, array{accountId: string, name: string}>
     */
    public array $tagManagerAccounts = [];

    public function settings(): GoogleSiteAnalyticsSetting
    {
        return GoogleSiteAnalyticsSetting::current();
    }

    public function connectUrl(): string
    {
        return route('google-site-analytics.connect');
    }

    public function loadAnalyticsAccounts(): void
    {
        try {
            $this->analyticsAccounts = app(GoogleSiteAnalyticsService::class)->listAnalyticsAccounts();

            if (empty($this->analyticsAccounts)) {
                Notification::make()
                    ->title('No Analytics account found')
                    ->body('Visit analytics.google.com once to create your first Analytics account (no property setup needed there) — this only has to happen once, ever.')
                    ->warning()
                    ->send();
            }
        } catch (RuntimeException $e) {
            Notification::make()->title('Could not load Analytics accounts')->body($e->getMessage())->danger()->send();
        }
    }

    public function setUpAnalytics(string $accountName): void
    {
        try {
            $result = app(GoogleSiteAnalyticsService::class)->setUpAnalytics($accountName);
        } catch (RuntimeException $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        Notification::make()->title($result['success'] ? 'GA4 set up' : 'Setup failed')->body($result['message'])->status($result['success'] ? 'success' : 'danger')->send();
    }

    public function verifySearchConsole(): void
    {
        try {
            $result = app(GoogleSiteAnalyticsService::class)->verifySearchConsole();
        } catch (RuntimeException $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        Notification::make()->title($result['success'] ? 'Search Console verified' : 'Verification incomplete')->body($result['message'])->status($result['success'] ? 'success' : 'warning')->send();
    }

    public function loadTagManagerAccounts(): void
    {
        try {
            $this->tagManagerAccounts = app(GoogleSiteAnalyticsService::class)->listTagManagerAccounts();

            if (empty($this->tagManagerAccounts)) {
                Notification::make()
                    ->title('No Tag Manager account found')
                    ->body('Visit tagmanager.google.com once to create your first account — this only has to happen once, ever.')
                    ->warning()
                    ->send();
            }
        } catch (RuntimeException $e) {
            Notification::make()->title('Could not load Tag Manager accounts')->body($e->getMessage())->danger()->send();
        }
    }

    public function setUpTagManager(string $accountId): void
    {
        try {
            $result = app(GoogleSiteAnalyticsService::class)->setUpTagManager($accountId);
        } catch (RuntimeException $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        Notification::make()->title($result['success'] ? 'Tag Manager set up' : 'Setup failed')->body($result['message'])->status($result['success'] ? 'success' : 'danger')->send();
    }

    public function disconnect(): void
    {
        app(GoogleSiteAnalyticsService::class)->disconnect();

        Notification::make()->title('Disconnected from Google')->success()->send();
    }
}
