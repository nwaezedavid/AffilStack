<?php

namespace App\Filament\Pages;

use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayManager;
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
 * Admin control panel for the payment gateways registered in
 * PaymentGatewayManager: enable/disable, enter credentials (stored
 * encrypted via PaymentGatewaySetting::$credentials), and verify them
 * against the live API before relying on them for real checkouts.
 *
 * Each gateway's "Verify credentials" button saves that gateway's current
 * form fields first, then calls PaymentGateway::verifyCredentials() —
 * verifying always tests what's currently in the form, not just what was
 * last saved.
 */
class PaymentGatewaySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?string $title = 'Payment Gateways';

    protected string $view = 'filament.pages.payment-gateway-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'flutterwave' => $this->stateFor('flutterwave'),
            'stripe' => $this->stateFor('stripe'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function stateFor(string $gateway): array
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        return [
            'is_enabled' => $settings->is_enabled,
            'secret_key' => $settings->credential('secret_key'),
            'public_key' => $settings->credential('public_key'),
            'secret_hash' => $settings->credential('secret_hash'),
            'publishable_key' => $settings->credential('publishable_key'),
            'webhook_secret' => $settings->credential('webhook_secret'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Flutterwave')
                    ->description($this->statusDescription('flutterwave'))
                    ->columns(2)
                    ->components([
                        Toggle::make('flutterwave.is_enabled')
                            ->label('Enabled')
                            ->helperText('Customers can only pay with an enabled gateway.')
                            ->columnSpanFull(),
                        TextInput::make('flutterwave.secret_key')
                            ->label('Secret key')
                            ->password()->revealable()
                            ->helperText('From the Flutterwave dashboard: Settings > API Keys.'),
                        TextInput::make('flutterwave.public_key')
                            ->label('Public key'),
                        TextInput::make('flutterwave.secret_hash')
                            ->label('Webhook secret hash')
                            ->password()->revealable()
                            ->helperText('Settings > Webhooks — not your API secret key.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Stripe')
                    ->description($this->statusDescription('stripe'))
                    ->columns(2)
                    ->components([
                        Toggle::make('stripe.is_enabled')
                            ->label('Enabled')
                            ->helperText('Customers can only pay with an enabled gateway.')
                            ->columnSpanFull(),
                        TextInput::make('stripe.secret_key')
                            ->label('Secret key')
                            ->password()->revealable()
                            ->helperText('From the Stripe dashboard: Developers > API keys.'),
                        TextInput::make('stripe.publishable_key')
                            ->label('Publishable key'),
                        TextInput::make('stripe.webhook_secret')
                            ->label('Webhook signing secret')
                            ->password()->revealable()
                            ->helperText('Developers > Webhooks > your endpoint — starts "whsec_".')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function statusDescription(string $gateway): string
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        if (! $settings->last_verified_at) {
            return 'Credentials have not been verified yet.';
        }

        $when = $settings->last_verified_at->diffForHumans();

        return match ($settings->last_verification_status) {
            'success' => "✓ Verified {$when} — {$settings->last_verification_message}",
            default => "✗ Verification failed {$when} — {$settings->last_verification_message}",
        };
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['flutterwave', 'stripe'] as $gateway) {
            $this->persist($gateway, $data[$gateway] ?? []);
        }

        Notification::make()->title('Payment gateway settings saved')->success()->send();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function persist(string $gateway, array $fields): void
    {
        PaymentGatewaySetting::forGateway($gateway)->update([
            'is_enabled' => (bool) ($fields['is_enabled'] ?? false),
            'credentials' => array_filter([
                'secret_key' => $fields['secret_key'] ?? null,
                'public_key' => $fields['public_key'] ?? null,
                'secret_hash' => $fields['secret_hash'] ?? null,
                'publishable_key' => $fields['publishable_key'] ?? null,
                'webhook_secret' => $fields['webhook_secret'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function verify(string $gateway, PaymentGatewayManager $gateways): void
    {
        $data = $this->form->getState();
        $this->persist($gateway, $data[$gateway] ?? []);

        $result = $gateways->get($gateway)->verifyCredentials();

        PaymentGatewaySetting::forGateway($gateway)->update([
            'last_verified_at' => now(),
            'last_verification_status' => $result['success'] ? 'success' : 'failed',
            'last_verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'Credentials verified' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify_flutterwave')
                ->label('Verify Flutterwave')
                ->color('gray')
                ->action(fn (PaymentGatewayManager $gateways) => $this->verify('flutterwave', $gateways)),
            Action::make('verify_stripe')
                ->label('Verify Stripe')
                ->color('gray')
                ->action(fn (PaymentGatewayManager $gateways) => $this->verify('stripe', $gateways)),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
