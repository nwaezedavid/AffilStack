<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\SiteSetting;
use App\Support\Marketplace\MarketplaceSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Feature 7: the marketplace's switch, the developers' revenue share,
 * how long earnings wait before payout, and the developer terms.
 */
class MarketplaceOptions extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'marketplace';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Marketplace settings';

    protected static ?string $title = 'Marketplace settings';

    protected static ?string $slug = 'marketplace-settings';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.marketplace-options';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => MarketplaceSettings::enabled(),
            'revenue_share' => MarketplaceSettings::revenueSharePercent(),
            'hold_days' => MarketplaceSettings::holdDays(),
            'terms' => trim((string) SiteSetting::get(MarketplaceSettings::TERMS, '')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Marketplace')->components([
                    Toggle::make('enabled')->label('Show the marketplace')
                        ->helperText('Off: the marketplace pages are hidden from customers. Installed add-ons keep working.'),
                ]),
                Section::make('Developer payments')->columns(2)->components([
                    TextInput::make('revenue_share')->label('Developer share (%)')->numeric()->minValue(0)->maxValue(100)->required()
                        ->helperText('Of each add-on payment. Applies to future payments; set a different share for one developer under Developers.'),
                    TextInput::make('hold_days')->label('Days before earnings can be paid out')->numeric()->minValue(0)->maxValue(120)->required()
                        ->helperText('Covers the refund window.'),
                ]),
                Section::make('Developer terms')->components([
                    Textarea::make('terms')->hiddenLabel()->rows(14)
                        ->placeholder(MarketplaceSettings::defaultTerms())
                        ->helperText('Shown when someone registers as a developer. Leave blank to use the standard terms shown here.'),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SiteSetting::setFlag(MarketplaceSettings::ENABLED, (bool) $state['enabled']);
        SiteSetting::set(MarketplaceSettings::REVENUE_SHARE, (string) max(0, min(100, (float) $state['revenue_share'])));
        SiteSetting::set(MarketplaceSettings::HOLD_DAYS, (string) max(0, (int) $state['hold_days']));
        SiteSetting::set(MarketplaceSettings::TERMS, trim((string) ($state['terms'] ?? '')));

        Notification::make()->title('Marketplace settings saved')->success()->send();
    }
}
