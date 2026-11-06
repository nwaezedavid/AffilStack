<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Filament\Resources\MobileDevices\MobileDeviceResource;
use App\Models\MobileDevice;
use App\Models\MobileIntegrationSetting;
use App\Services\Mobile\Push\MobilePush;
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
 * Site > App Push & Links: switches phone notifications on, holds the
 * optional Expo access token (encrypted, never shown again — see
 * WritesMaskedCredentials), and the two store IDs that let AffilStack web
 * links open the app (served from /.well-known/ by AppLinksController).
 */
class MobilePushSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    public const FINGERPRINT_PATTERN = '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/';

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'App Push & Links';

    protected static ?string $title = 'App Push & Links';

    protected string $view = 'filament.pages.mobile-push-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = MobileIntegrationSetting::current();

        $this->form->fill([
            'push_enabled' => $settings->push_enabled,
            // Never the real token — see WritesMaskedCredentials.
            'expo_access_token' => null,
            'ios_team_id' => $settings->ios_team_id,
            'android_sha256_fingerprints' => implode("\n", $settings->androidFingerprints()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = MobileIntegrationSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Phone notifications')
                    ->description($this->pushStatus())
                    ->columns(2)
                    ->components([
                        Toggle::make('push_enabled')
                            ->label('Send notifications to customers\' phones')
                            ->helperText('When content is ready, a buyer replies, an AI agent needs approval, and the other kinds customers choose in the app (More → Notifications). Off: nothing is sent.')
                            ->columnSpanFull(),
                        TextInput::make('expo_access_token')
                            ->label('Expo access token (optional)')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('expo_access_token'))))
                            ->helperText('Only needed if you turn on "Enhanced push security" in your Expo account. expo.dev → Account settings → Access tokens → Create. Stored encrypted; never shown again.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Open links in the app')
                    ->description('When these are filled in and the apps are published, tapping an AffilStack link (in an email, for example) opens the app on that screen instead of the browser. Sign-in always stays in the browser.')
                    ->columns(2)
                    ->components([
                        TextInput::make('ios_team_id')
                            ->label('Apple Team ID')
                            ->placeholder('A1B2C3D4E5')
                            ->regex('/^[A-Za-z0-9]{10}$/')
                            ->validationMessages(['regex' => 'The Team ID is 10 letters and numbers, shown at the top right of developer.apple.com/account.'])
                            ->helperText('10 characters, top right of your Apple Developer account. The same one as on Site → Apple Login.'),
                        Textarea::make('android_sha256_fingerprints')
                            ->label('Android SHA-256 signing fingerprints')
                            ->rows(3)
                            ->placeholder('AB:CD:EF:…  (one per line)')
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                foreach ($this->fingerprintLines((string) $value) as $line) {
                                    if (! preg_match(self::FINGERPRINT_PATTERN, $line)) {
                                        $fail('Each line should be a SHA-256 fingerprint: 32 pairs of letters/numbers separated by colons.');

                                        return;
                                    }
                                }
                            })
                            ->helperText('Google Play Console → your app → Test and release → App integrity → App signing → "SHA-256 certificate fingerprint". Add the upload key\'s one too if you test builds outside Google Play.'),
                        Placeholder::make('apple_file')
                            ->label('iPhone link file')
                            ->content(fn (): string => URL::route('app-links.apple')),
                        Placeholder::make('android_file')
                            ->label('Android link file')
                            ->content(fn (): string => URL::route('app-links.android')),
                    ]),
            ]);
    }

    protected function pushStatus(): string
    {
        $phones = MobileDevice::query()->active()->where('push_provider', 'expo')->whereNotNull('push_token_hash')->count();
        $reach = $phones === 1 ? '1 phone has' : number_format($phones).' phones have';

        return MobileIntegrationSetting::current()->push_enabled
            ? "On. {$reach} allowed notifications."
            : "Off. {$reach} allowed notifications and will start receiving them when you switch this on.";
    }

    /**
     * @return array<int, string>
     */
    protected function fingerprintLines(string $value): array
    {
        return array_values(array_filter(array_map(fn (string $line): string => strtoupper(trim($line)), preg_split('/[\r\n,]+/', $value) ?: [])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = MobileIntegrationSetting::current();

        $settings->update([
            'push_enabled' => (bool) ($data['push_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'expo_access_token' => isset($data['expo_access_token']) ? trim((string) $data['expo_access_token']) : null,
            ], ['expo_access_token']),
            'ios_team_id' => filled($data['ios_team_id'] ?? null) ? strtoupper(trim((string) $data['ios_team_id'])) : null,
            'android_sha256_fingerprints' => array_values(array_unique($this->fingerprintLines((string) ($data['android_sha256_fingerprints'] ?? '')))) ?: null,
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('App push & links settings saved')->success()->send();
    }

    /**
     * Sends a test notification to the signed-in admin's own phones.
     */
    public function sendTest(): void
    {
        $devices = app(MobilePush::class)->devicesFor(auth()->user())->get();

        if ($devices->isEmpty()) {
            Notification::make()
                ->title('No phone to send to')
                ->body('Sign in to the AffilStack app on your phone with this admin account and allow notifications, then try again.')
                ->warning()
                ->send();

            return;
        }

        $results = $devices->map(fn (MobileDevice $device): array => app(MobilePush::class)->sendTest($device));
        $sent = $results->where('success', true)->count();

        Notification::make()
            ->title($sent > 0 ? "Sent to {$sent} of {$devices->count()} phone(s)" : 'Not sent')
            ->body($results->firstWhere('success', false)['message'] ?? 'It should arrive within a few seconds.')
            ->status($sent > 0 ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send a test to my phone')
                ->color('gray')
                ->action(fn () => $this->sendTest()),
            Action::make('devices')
                ->label('Signed-in phones')
                ->color('gray')
                ->visible(fn (): bool => MobileDeviceResource::canAccess())
                ->url(fn (): string => MobileDeviceResource::getUrl()),
        ];
    }
}
