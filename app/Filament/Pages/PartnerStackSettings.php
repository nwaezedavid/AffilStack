<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\PartnerStackSetting;
use App\Services\Referrals\PartnerStackClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin control for a future PartnerStack connection — deliberately
 * connection-only: store and verify a PartnerStack API key pair ahead of
 * time, so it's ready to flip on whenever you decide how to use it,
 * without touching the in-house referral program (Filament: Billing >
 * Referral Payouts / Referral Commissions), which keeps running exactly
 * as-is and independently of this page.
 */
class PartnerStackSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'PartnerStack';

    protected static ?string $title = 'PartnerStack Connection';

    protected string $view = 'filament.pages.partner-stack-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = PartnerStackSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'public_key' => $settings->credential('public_key'),
            'secret_key' => $settings->credential('secret_key'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('PartnerStack')
                    ->description($this->statusDescription())
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Connection enabled')
                            ->helperText('Right now this only stores and verifies credentials — no data syncs anywhere yet. Your in-house referral program is unaffected either way.')
                            ->columnSpanFull(),
                        TextInput::make('public_key')
                            ->label('Public key')
                            ->helperText('From your PartnerStack dashboard: Settings > API Keys.'),
                        TextInput::make('secret_key')
                            ->label('Secret key')
                            ->password()->revealable(),
                    ]),
            ]);
    }

    protected function statusDescription(): string
    {
        $settings = PartnerStackSetting::current();

        if (! $settings->verified_at) {
            return 'Not verified yet. The in-house referral program keeps running independently of this connection.';
        }

        $when = $settings->verified_at->diffForHumans();

        return $settings->verification_status === 'success'
            ? "✓ Verified {$when} — {$settings->verification_message}"
            : "✗ Verification failed {$when} — {$settings->verification_message}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        PartnerStackSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => array_filter([
                'public_key' => $data['public_key'] ?? null,
                'secret_key' => $data['secret_key'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('PartnerStack settings saved')->success()->send();
    }

    public function verify(): void
    {
        $this->persist($this->form->getState());
        $settings = PartnerStackSetting::current();

        $result = (new PartnerStackClient(
            (string) $settings->credential('public_key'),
            (string) $settings->credential('secret_key'),
        ))->verifyCredentials();

        $settings->update([
            'verified_at' => now(),
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'PartnerStack connected' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify connection')
                ->color('gray')
                ->action(fn () => $this->verify()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
