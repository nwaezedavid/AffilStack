<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentCredentialAdvisor;
use App\Services\Payments\PaymentGatewayManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Admin control panel for the payment gateways registered in
 * PaymentGatewayManager: enable/disable, enter credentials (stored
 * encrypted via PaymentGatewaySetting::$credentials), and verify them
 * against the live API before relying on them for real checkouts.
 *
 * Redesign (task #164 — "difficult to understand"): each gateway used to
 * dump its "Verify"/"Explain with AI" buttons into one disconnected list
 * at the very top of the page, far from that gateway's own fields, and its
 * verification status was a single plain-text line. Every gateway is now a
 * fully self-contained unit — its own icon, its own colored status badge,
 * and its own Verify/Explain buttons live in that gateway's own section
 * header (Section::headerActions(), which Filament renders inline with
 * that section's heading) — so "which settings are which" is answered by
 * simply looking at one section at a time instead of cross-referencing a
 * page-level button list against four unlabeled panels below it.
 *
 * Each gateway's "Verify credentials" button saves that gateway's current
 * form fields first, then calls PaymentGateway::verifyCredentials() —
 * verifying always tests what's currently in the form, not just what was
 * last saved.
 */
class PaymentGatewaySettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'billing';

    /**
     * Super-admin only: swapping in another merchant account's keys (and
     * webhook secret) would keep checkout working while every payment lands
     * in someone else's account.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

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
     * Every gateway this page manages, plus the display metadata (label,
     * icon, and which customers it's shown to) that both the form sections
     * and the blade view's summary strip read from — adding a gateway here
     * is the only change needed to get it a full section + status card.
     *
     * @var array<string, array{label: string, icon: string, shownTo: string}>
     */
    protected static array $gateways = [
        'flutterwave' => ['label' => 'Flutterwave', 'icon' => 'heroicon-o-globe-alt', 'shownTo' => 'International customers (USD)'],
        'stripe' => ['label' => 'Stripe', 'icon' => 'heroicon-o-credit-card', 'shownTo' => 'International customers (USD)'],
        'paystack' => ['label' => 'Paystack', 'icon' => 'heroicon-o-banknotes', 'shownTo' => 'Customers checking out from Nigeria (NGN)'],
        'paypal' => ['label' => 'PayPal', 'icon' => 'heroicon-o-currency-dollar', 'shownTo' => 'International customers (USD)'],
    ];

    /**
     * Every credentials key, across every gateway, that's rendered as a
     * masked `->password()` field — see WritesMaskedCredentials. None of
     * these ever get their real value put into form state on mount().
     *
     * @var array<int, string>
     */
    protected static array $secretCredentialKeys = ['secret_key', 'secret_hash', 'webhook_secret', 'client_secret'];

    public function mount(): void
    {
        $this->form->fill(collect(array_keys(static::$gateways))->mapWithKeys(fn (string $gateway) => [$gateway => $this->stateFor($gateway)])->all());
    }

    /**
     * Read by the blade view's summary strip — one card per gateway with
     * its enabled/verified state, so the whole payment setup is visible at
     * a glance before scrolling into any single gateway's own fields.
     *
     * @return array<int, array{key: string, label: string, icon: string, shownTo: string, isEnabled: bool, status: string, statusLabel: string}>
     */
    public function summaries(): array
    {
        return collect(static::$gateways)->map(function (array $meta, string $key) {
            $settings = PaymentGatewaySetting::forGateway($key);

            $status = match (true) {
                ! $settings->last_verified_at => 'unverified',
                $settings->last_verification_status === 'success' => 'verified',
                default => 'failed',
            };

            return [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'shownTo' => $meta['shownTo'],
                'isEnabled' => $settings->is_enabled,
                'status' => $status,
                'statusLabel' => match ($status) {
                    'verified' => 'Verified',
                    'failed' => 'Verification failed',
                    default => 'Not verified yet',
                },
            ];
        })->values()->all();
    }

    /**
     * Never puts a secret's real value into form state — see
     * WritesMaskedCredentials. Only non-secret fields (public_key,
     * publishable_key, usd_to_ngn_rate, client_id, webhook_id) mount with
     * their real stored value.
     *
     * @return array<string, mixed>
     */
    protected function stateFor(string $gateway): array
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        return [
            'is_enabled' => $settings->is_enabled,
            'secret_key' => null,
            'public_key' => $settings->credential('public_key'),
            'secret_hash' => null,
            'publishable_key' => $settings->credential('publishable_key'),
            'webhook_secret' => null,
            'usd_to_ngn_rate' => $settings->credential('usd_to_ngn_rate'),
            'client_id' => $settings->credential('client_id'),
            'client_secret' => null,
            'webhook_id' => $settings->credential('webhook_id'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->gatewaySection('flutterwave', [
                    Toggle::make('flutterwave.is_enabled')
                        ->label('Enabled')
                        ->helperText('Customers can only pay with an enabled gateway.')
                        ->columnSpanFull(),
                    TextInput::make('flutterwave.secret_key')
                        ->label('Secret key')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('flutterwave')->credential('secret_key'))))
                        ->helperText('From the Flutterwave dashboard: Settings > API Keys.'),
                    TextInput::make('flutterwave.public_key')
                        ->label('Public key'),
                    TextInput::make('flutterwave.secret_hash')
                        ->label('Webhook secret hash')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('flutterwave')->credential('secret_hash'))))
                        ->helperText('Settings > Webhooks — not your API secret key.')
                        ->columnSpanFull(),
                ]),

                $this->gatewaySection('stripe', [
                    Toggle::make('stripe.is_enabled')
                        ->label('Enabled')
                        ->helperText('Customers can only pay with an enabled gateway.')
                        ->columnSpanFull(),
                    TextInput::make('stripe.secret_key')
                        ->label('Secret key')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('stripe')->credential('secret_key'))))
                        ->helperText('From the Stripe dashboard: Developers > API keys.'),
                    TextInput::make('stripe.publishable_key')
                        ->label('Publishable key'),
                    TextInput::make('stripe.webhook_secret')
                        ->label('Webhook signing secret')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('stripe')->credential('webhook_secret'))))
                        ->helperText('Developers > Webhooks > your endpoint — starts "whsec_".')
                        ->columnSpanFull(),
                ]),

                $this->gatewaySection('paystack', [
                    Toggle::make('paystack.is_enabled')
                        ->label('Enabled')
                        ->helperText('Shown only to customers checking out from Nigeria, paying in Naira.')
                        ->columnSpanFull(),
                    TextInput::make('paystack.secret_key')
                        ->label('Secret key')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('paystack')->credential('secret_key'))))
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

                $this->gatewaySection('paypal', [
                    Toggle::make('paypal.is_enabled')
                        ->label('Enabled')
                        ->helperText('Customers can only pay with an enabled gateway.')
                        ->columnSpanFull(),
                    TextInput::make('paypal.client_id')
                        ->label('Client ID')
                        ->helperText('From the PayPal Developer Dashboard: your app\'s Client ID.'),
                    TextInput::make('paypal.client_secret')
                        ->label('Client secret')
                        ->password()->revealable()
                        ->placeholder($this->maskedPlaceholder(filled(PaymentGatewaySetting::forGateway('paypal')->credential('client_secret')))),
                    TextInput::make('paypal.webhook_id')
                        ->label('Webhook ID')
                        ->helperText('Your app\'s Webhooks tab — needed to verify that a webhook really came from PayPal.')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    /**
     * Builds one gateway's whole self-contained section: icon + heading,
     * colored status badge as the description, its own Verify/Explain
     * header actions, and the credential fields passed in — the single
     * place that wires all of that together so each of the 4 calls above
     * only has to supply what's actually different about that gateway.
     *
     * @param  array<int, Component>  $fields
     */
    protected function gatewaySection(string $gateway, array $fields): Section
    {
        $label = static::$gateways[$gateway]['label'];

        return Section::make($label)
            ->icon(static::$gateways[$gateway]['icon'])
            ->description($this->statusDescription($gateway))
            ->collapsible()
            ->headerActions([
                Action::make("verify_{$gateway}")
                    ->label('Verify credentials')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->action(fn (PaymentGatewayManager $gateways) => $this->verify($gateway, $gateways)),
                Action::make("explain_{$gateway}")
                    ->label('Explain with AI')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->visible(fn () => PaymentGatewaySetting::forGateway($gateway)->last_verification_status === 'failed')
                    ->action(fn (PaymentCredentialAdvisor $advisor) => $this->explainFailure($gateway, $advisor)),
            ])
            ->columns(2)
            ->components($fields);
    }

    /**
     * A small colored status badge (green/red/gray, matching the badge
     * styling every other admin settings page in this app already uses —
     * see resources/views/filament/pages/_panel-styles.blade.php) followed
     * by the verification detail — replacing the old plain-text "✓
     * Verified..."/"✗ Verification failed..." line, which read as an error
     * message even when everything was working fine.
     *
     * Section::description() renders whatever HTML this returns outside of
     * Blade/Tailwind compilation, so the badge is built with inline styles
     * rather than utility classes — the same reason _panel-styles.blade.php
     * ships plain CSS instead of relying on Tailwind classes being present
     * in Filament's own compiled bundle.
     */
    protected function statusDescription(string $gateway): HtmlString
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        if (! $settings->last_verified_at) {
            return new HtmlString(
                $this->badge('Not verified yet', '#f3f4f6', '#6b7280').
                ' <span style="color:#6b7280;font-size:0.8125rem;">Enter your credentials below, then click "Verify credentials" above.</span>'
            );
        }

        $when = e($settings->last_verified_at->diffForHumans());
        $message = e((string) $settings->last_verification_message);

        $html = $settings->last_verification_status === 'success'
            ? $this->badge('Verified', '#dcfce7', '#15803d')." <span style=\"color:#6b7280;font-size:0.8125rem;\">{$when} — {$message}</span>"
            : $this->badge('Verification failed', '#fee2e2', '#b91c1c')." <span style=\"color:#6b7280;font-size:0.8125rem;\">{$when} — {$message}</span>";

        if ($explanation = $this->aiExplanations[$gateway] ?? null) {
            $html .= '<br><span style="color:#2563eb;font-size:0.8125rem;">✦ AI assistant: '.e($explanation).'</span>';
        }

        return new HtmlString($html);
    }

    protected function badge(string $label, string $background, string $color): string
    {
        return '<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:0.125rem 0.625rem;font-size:0.75rem;font-weight:600;background:'.$background.';color:'.$color.';">'.e($label).'</span>';
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(static::$gateways) as $gateway) {
            $this->persist($gateway, $data[$gateway] ?? []);
        }

        Notification::make()->title('Payment gateway settings saved')->success()->send();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function persist(string $gateway, array $fields): void
    {
        $settings = PaymentGatewaySetting::forGateway($gateway);

        $settings->update([
            'is_enabled' => (bool) ($fields['is_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'secret_key' => $fields['secret_key'] ?? null,
                'public_key' => $fields['public_key'] ?? null,
                'secret_hash' => $fields['secret_hash'] ?? null,
                'publishable_key' => $fields['publishable_key'] ?? null,
                'webhook_secret' => $fields['webhook_secret'] ?? null,
                'usd_to_ngn_rate' => $fields['usd_to_ngn_rate'] ?? null,
                'client_id' => $fields['client_id'] ?? null,
                'client_secret' => $fields['client_secret'] ?? null,
                'webhook_id' => $fields['webhook_id'] ?? null,
            ], static::$secretCredentialKeys),
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

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
