<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\IntegrationFundingSetting;
use App\Services\Settings\FundingHealthChecker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Vault, the funding-monitor agent: "an AI Agent... that will monitor all
 * the third-party APIs and platforms that requires my to find the account
 * or buy more credit, and then remind me on time to recharge or fund my
 * account... to ensure that my users will not get interrupted". One page
 * showing every funding-risk item's current state (live-checked where an
 * account has a real balance endpoint, a manual reminder cadence where it
 * doesn't) plus admin-editable thresholds/cadences. See
 * FundingHealthChecker for exactly what each item checks and how repeat
 * notifications are avoided.
 */
class FundingHealth extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'ai_agents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Funding Watch';

    protected static ?string $title = 'Funding Watch (Vault)';

    protected string $view = 'filament.pages.funding-health';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->refreshItems();

        $funding = IntegrationFundingSetting::current();

        $this->form->fill([
            'heygen_low_balance_threshold' => $funding->heygen_low_balance_threshold,
            'anthropic_reminder_days' => $funding->anthropic_reminder_days,
            'meta_ads_reminder_days' => $funding->meta_ads_reminder_days,
            'wallet_thresholds' => collect((array) $funding->wallet_thresholds_cents)
                ->map(fn ($cents, $currency) => ['currency' => $currency, 'amount' => $cents / 100])
                ->values()
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('HeyGen (live-checked)')
                    ->components([
                        TextInput::make('heygen_low_balance_threshold')
                            ->label('Low-balance threshold (credits)')
                            ->numeric()->minValue(0)->required()
                            ->helperText('Vault alerts every full admin the moment HeyGen\'s remaining credits drop below this.'),
                    ]),
                Section::make('Payout wallet (live-checked)')
                    ->components([
                        Repeater::make('wallet_thresholds')
                            ->label('Low-balance thresholds')
                            ->schema([
                                Select::make('currency')
                                    ->options(['USD' => 'USD', 'NGN' => 'NGN'])
                                    ->required(),
                                TextInput::make('amount')
                                    ->label('Threshold')
                                    ->numeric()->minValue(0)->step(0.01)->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add currency threshold')
                            ->helperText('Vault alerts every full admin the moment a currency\'s payout wallet balance drops below its threshold — leave a currency out to never be alerted for it.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Manual reminders')
                    ->description('These accounts have no balance an API key alone can check — Vault reminds you on a cadence instead of pretending to have verified anything.')
                    ->columns(2)
                    ->components([
                        TextInput::make('anthropic_reminder_days')
                            ->label('Claude / Anthropic usage — remind every (days)')
                            ->numeric()->minValue(1)->required(),
                        TextInput::make('meta_ads_reminder_days')
                            ->label('Meta ad spend — remind every (days)')
                            ->numeric()->minValue(1)->required(),
                    ]),
            ]);
    }

    protected function refreshItems(): void
    {
        $this->items = app(FundingHealthChecker::class)->items();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        IntegrationFundingSetting::current()->update([
            'heygen_low_balance_threshold' => (int) $data['heygen_low_balance_threshold'],
            'anthropic_reminder_days' => (int) $data['anthropic_reminder_days'],
            'meta_ads_reminder_days' => (int) $data['meta_ads_reminder_days'],
            'wallet_thresholds_cents' => collect($data['wallet_thresholds'] ?? [])
                ->filter(fn (array $row) => filled($row['currency'] ?? null))
                ->mapWithKeys(fn (array $row) => [
                    strtoupper((string) $row['currency']) => (int) round(((float) ($row['amount'] ?? 0)) * 100),
                ])
                ->all(),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());
        $this->refreshItems();

        Notification::make()->title('Funding Watch settings saved')->success()->send();
    }

    public function runChecks(): void
    {
        app(FundingHealthChecker::class)->runChecks();
        $this->refreshItems();

        Notification::make()->title('Funding checks complete')->success()->send();
    }

    public function acknowledgeReminder(string $key): void
    {
        $funding = IntegrationFundingSetting::current();

        match ($key) {
            'anthropic' => $funding->update([
                'anthropic_reminder_last_acknowledged_at' => now(),
                'anthropic_reminder_notified_at' => null,
            ]),
            'meta_ads' => $funding->update([
                'meta_ads_reminder_last_acknowledged_at' => now(),
                'meta_ads_reminder_notified_at' => null,
            ]),
            default => null,
        };

        $this->refreshItems();

        Notification::make()->title('Reminder reset')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label('Run checks now')
                ->icon(Heroicon::OutlinedBolt)
                ->action(fn () => $this->runChecks()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
