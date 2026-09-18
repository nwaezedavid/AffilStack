<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Services\Settings\ConnectionAdvisor;
use App\Services\Settings\SettingsHealthChecker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * "Every admin setting should have an active AI Agent assisting and
 * verifying every connection, to make sure everything is setup correctly
 * and working well in the frontend" (audit item #6) — one page listing
 * every external integration this app's settings pages configure, its
 * current status, and an AI explanation on demand for anything failing or
 * still incomplete. See SettingsHealthChecker for what each item actually
 * checks and why some can only ever report "configured", not "verified".
 */
class ConnectionsHealth extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Connections Health';

    protected static ?string $title = 'Connections Health';

    protected string $view = 'filament.pages.connections-health';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * @var array{success: bool, message: string}|null
     */
    public ?array $googleResult = null;

    /**
     * @var array<string, string>
     */
    public array $aiExplanations = [];

    public function mount(): void
    {
        $this->items = app(SettingsHealthChecker::class)->items();
    }

    public function runChecks(): void
    {
        $this->googleResult = app(SettingsHealthChecker::class)->runLiveChecks();
        $this->items = app(SettingsHealthChecker::class)->items($this->googleResult);

        $failures = collect($this->items)->filter(fn (array $item) => $item['live_checkable'] && $item['success'] === false)->count();

        Notification::make()
            ->title($failures === 0 ? 'All live-checkable connections passed' : "{$failures} connection(s) failed — see below")
            ->status($failures === 0 ? 'success' : 'danger')
            ->send();
    }

    public function explain(string $key): void
    {
        $item = collect($this->items)->firstWhere('key', $key);

        if (! $item) {
            return;
        }

        $status = $item['message'] ?? ($item['is_configured'] ? 'Configured, not yet verified.' : 'Not configured yet.');

        $this->aiExplanations[$key] = app(ConnectionAdvisor::class)->explain($item['label'], $item['what_it_does'], $status);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label('Run all live checks')
                ->icon(Heroicon::OutlinedBolt)
                ->action(fn () => $this->runChecks()),
        ];
    }
}
