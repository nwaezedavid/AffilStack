<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentCredentialAdvisor;
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
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?string $title = 'Payment Gateways';

    protected string $view = 'filament.pages.payment-gateway-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * AI explanations of the most recent failed verification, keyed by
     * gateway — populated on demand via the "Explain with AI" action so a
     * non-technical admin isn't left staring at a raw HTTP error.
     *
     * @var array<string, string>
     */
    public array $aiExplanations = [];

    /**
     * Every gateway this page manages — the single list the mount/save/
     * header-actions loops all read from, so adding a gateway here is the
     * only change needed to get it a form section + verify/explain buttons.
     *
     * @var array<int, string>
     */
    protected static array $gatewayKeys = ['flutterwave', 'stripe', 'paystack', 'paypal'];

    public function mount(): void
    {
        $this->form->fill(collect(static::$gatewayKeys)->mapWithKeys(fn (string $gateway) => [$gateway => $this->stateFor($gateway)])->all());
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
            'usd_to_ngn_rate' => $settings->credential('usd_to_ngn_rate'),
            'client_id' => $settings->credential('client_id'),
            'client_secret' => $settings->credential('client_secret'),
            'webhook_id' => $settings->credential('webhook_id'),
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

                Section::make('Paystack')
                    ->description($this->statusDescription('paystack'))
                    ->columns(2)
                    ->components([
                        Toggle::make('paystack.is_enabled')
                            ->label('Enabled')
                            ->helperText('Shown only to customers checking out from Nigeria, paying in Naira.')
                            ->columnSpanFull(),
                        TextInput::make('paystack.secret_key')
                            ->label('Secret key')
                            ->password()->revealable()
                            ->helperText('From the Paystack dashboard: Settings > API Keys & Webhooks.'),
                        TextInput::make('paystack.public_key')
                            ->label('Public key'),
                        TextInput::make('paystack.usd_to_ngn_rate')
                            ->label('USD → NGN exchange rate')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Every plan is priced in USD — this is what it gets multiplied by to charge Nigerian cards in Naira. There is no live rate feed, so keep this current yourself.')
                            ->columnSpanFull(),
                    ]),

                Section::make('PayPal')
                    ->description($this->statusDescription('paypal'))
                    ->columns(2)
                    ->components([
                        Toggle::make('paypal.is_enabled')
                            ->label('Enabled')
                            ->helperText('Customers can only pay with an enabled gateway.')
                            ->columnSpanFull(),
                        TextInput::make('paypal.client_id')
                            ->label('Client ID')
                            ->helperText('From the PayPal Developer Dashboard: your app\'s Client ID.'),
                        TextInput::make('paypal.client_secret')
                            ->label('Client secret')
                            ->password()->revealable(),
                        TextInput::make('paypal.webhook_id')
                            ->label('Webhook ID')
                            ->helperText('Your app\'s Webhooks tab — needed to verify that a webhook really came from PayPal.')
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

        $line = match ($settings->last_verification_status) {
            'success' => "✓ Verified {$when} — {$settings->last_verification_message}",
            default => "✗ Verification failed {$when} — {$settings->last_verification_message}",
        };

        if ($explanation = $this->aiExplanations[$gateway] ?? null) {
            $line .= " — AI assistant: {$explanation}";
        }

        return $line;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (static::$gatewayKeys as $gateway) {
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
                'usd_to_ngn_rate' => $fields['usd_to_ngn_rate'] ?? null,
                'client_id' => $fields['client_id'] ?? null,
                'client_secret' => $fields['client_secret'] ?? null,
                'webhook_id' => $fields['webhook_id'] ?? null,
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

    /**
     * Explains the gateway's last failed verification in plain English via
     * the app's existing AI provider abstraction (task #83). Only the
     * gateway name and the already-sanitized failure message are sent —
     * never any credential, entered or stored.
     */
    public function explainFailure(string $gateway, PaymentCredentialAdvisor $advisor): void
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        if ($settings->last_verification_status !== 'failed' || ! $settings->last_verification_message) {
            return;
        }

        $this->aiExplanations[$gateway] = $explanation = $advisor->explain($gateway, $settings->last_verification_message);

        Notification::make()
            ->title(ucfirst($gateway).' verification — AI explanation')
            ->body($explanation)
            ->info()
            ->persistent()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return collect(static::$gatewayKeys)
            ->flatMap(fn (string $gateway) => [
                Action::make("verify_{$gateway}")
                    ->label('Verify '.ucfirst($gateway))
                    ->color('gray')
                    ->action(fn (PaymentGatewayManager $gateways) => $this->verify($gateway, $gateways)),
                Action::make("explain_{$gateway}")
                    ->label('Explain with AI')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->visible(fn () => PaymentGatewaySetting::forGateway($gateway)->last_verification_status === 'failed')
                    ->action(fn (PaymentCredentialAdvisor $advisor) => $this->explainFailure($gateway, $advisor)),
            ])
            ->all();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
