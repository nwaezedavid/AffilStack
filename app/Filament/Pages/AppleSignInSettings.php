<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\AppleOauthSetting;
use App\Services\Auth\AppleSignInService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

/**
 * Site > Apple Login: the keys "Continue with Apple" needs, from the
 * owner's Apple Developer account. Apple requires this option in the
 * iPhone app because the app offers Google sign-in. The private key is
 * encrypted at rest and never shown again after saving (see
 * WritesMaskedCredentials), like every other credential page.
 */
class AppleSignInSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Apple Login';

    protected static ?string $title = 'Apple Login';

    protected string $view = 'filament.pages.apple-sign-in-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = AppleOauthSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'services_id' => $settings->credential('services_id'),
            'team_id' => $settings->credential('team_id'),
            'key_id' => $settings->credential('key_id'),
            // Never the real key — see WritesMaskedCredentials.
            'private_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = AppleOauthSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sign in with Apple')
                    ->description($this->statusDescription())
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->helperText('Shows "Continue with Apple" on the sign-in page — the page the AffilStack phone app opens to sign in.')
                            ->columnSpanFull(),
                        TextInput::make('services_id')
                            ->label('Services ID')
                            ->placeholder('com.affilstack.signin')
                            ->helperText('Certificates, Identifiers & Profiles → Identifiers → Services IDs. Its "Sign in with Apple" setting must list the domain and return URL below.'),
                        TextInput::make('team_id')
                            ->label('Team ID')
                            ->placeholder('A1B2C3D4E5')
                            ->helperText('10 characters, shown at the top right of your Apple Developer account.'),
                        TextInput::make('key_id')
                            ->label('Key ID')
                            ->placeholder('XYZ123ABC4')
                            ->helperText('Keys → the key with "Sign in with Apple" ticked → Key ID.'),
                        Textarea::make('private_key')
                            ->label('Private key (.p8 file contents)')
                            ->rows(5)
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('private_key'))) ?? "-----BEGIN PRIVATE KEY-----\n…\n-----END PRIVATE KEY-----")
                            ->helperText('Open the AuthKey_….p8 file you downloaded (Apple lets you download it only once) and paste everything, including the BEGIN and END lines. Stored encrypted; never shown again.')
                            ->columnSpanFull(),
                        Placeholder::make('domain')
                            ->label('Domain to register with Apple')
                            ->content(fn (): string => (string) parse_url(URL::route('apple.callback'), PHP_URL_HOST)),
                        Placeholder::make('return_url')
                            ->label('Return URL to register with Apple')
                            ->content(fn (): string => URL::route('apple.callback')),
                    ]),
            ]);
    }

    protected function statusDescription(): string
    {
        $apple = app(AppleSignInService::class);

        if (! $apple->isConfigured()) {
            return 'Not configured yet. Apple requires this before the iPhone app can be published.';
        }

        return $apple->isEnabled()
            ? '"Continue with Apple" is live on the sign-in page.'
            : 'Configured but turned off — hidden from visitors.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = AppleOauthSetting::current();

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'services_id' => isset($data['services_id']) ? trim((string) $data['services_id']) : null,
                'team_id' => isset($data['team_id']) ? strtoupper(trim((string) $data['team_id'])) : null,
                'key_id' => isset($data['key_id']) ? strtoupper(trim((string) $data['key_id'])) : null,
                'private_key' => isset($data['private_key']) ? trim((string) $data['private_key']) : null,
            ], ['private_key']),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Apple login settings saved')->success()->send();
    }

    public function verify(): void
    {
        $this->persist($this->form->getState());

        // Resolved after persist(): the service snapshots settings in its
        // constructor.
        $result = app(AppleSignInService::class)->verifyCredentials();

        Notification::make()
            ->title($result['success'] ? 'Looks good' : 'Check your keys')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Check keys')
                ->color('gray')
                ->action(fn () => $this->verify()),
        ];
    }
}
