<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\NetworkConnection;
use App\Models\SiteSetting;
use App\Services\Networks\Adapters\Digistore24Adapter;
use App\Services\Networks\NetworkRegistry;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;

/**
 * Feature 5: customers' affiliate network connections at a glance, and
 * AffilStack's Digistore24 developer key, which lets customers approve
 * Digistore24 access in the browser instead of pasting a key. The other
 * networks need nothing set up here: customers bring their own keys.
 */
class AffiliateNetworkSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Affiliate Networks';

    protected static ?string $title = 'Affiliate Networks';

    protected string $view = 'filament.pages.affiliate-network-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['digistore24_developer_key' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Customer connections')
                    ->description($this->summary()),
                Section::make('Digistore24 one-click approval')
                    ->description(Digistore24Adapter::canApproveInBrowser()
                        ? 'Set up: customers can approve Digistore24 access in the browser.'
                        : 'Optional. Without it, customers paste a Digistore24 API key instead.')
                    ->components([
                        TextInput::make('digistore24_developer_key')
                            ->label('Digistore24 developer key')
                            ->password()
                            ->revealable()
                            ->placeholder(Digistore24Adapter::canApproveInBrowser() ? '•••••••• saved (leave blank to keep)' : 'Not set')
                            ->helperText('In Digistore24: Settings → Account access → API keys → New API key, with "API permissions" set to Developer. A developer key can only request access for users; it can\'t read any account data.'),
                    ]),
            ]);
    }

    protected function summary(): string
    {
        $counts = NetworkConnection::query()->active()->selectRaw('network, count(*) as total')->groupBy('network')->pluck('total', 'network');
        $errors = NetworkConnection::query()->where('status', 'error')->count();

        if ($counts->isEmpty()) {
            return 'No customer has connected a network yet. Set how many networks each plan includes under Plans.';
        }

        $parts = collect(NetworkRegistry::keys())
            ->filter(fn ($network) => isset($counts[$network]))
            ->map(fn ($network) => NetworkRegistry::label($network).': '.$counts[$network])
            ->implode(' · ');

        return $parts.($errors ? " · {$errors} need attention (keys refused)" : '');
    }

    public function save(): void
    {
        $key = trim((string) ($this->form->getState()['digistore24_developer_key'] ?? ''));

        if ($key !== '') {
            SiteSetting::set(Digistore24Adapter::DEVELOPER_KEY_SETTING, Crypt::encryptString($key));
        }

        $this->form->fill(['digistore24_developer_key' => null]);

        Notification::make()->title('Affiliate network settings saved')->success()->send();
    }

    public function removeDigistoreKey(): void
    {
        SiteSetting::set(Digistore24Adapter::DEVELOPER_KEY_SETTING, '');

        Notification::make()->title('Digistore24 developer key removed')->success()->send();
    }
}
