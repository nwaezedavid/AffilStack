<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\GoogleOauthSetting;
use App\Services\Auth\GoogleOAuthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

/**
 * Admin control for "Continue with Google" (task #84): enable/disable,
 * enter the OAuth client id/secret (stored encrypted via
 * GoogleOauthSetting), and check they're at least correctly formatted.
 */
class GoogleOAuthSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Google Login';

    protected static ?string $title = 'Google Login';

    protected string $view = 'filament.pages.google-oauth-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = GoogleOauthSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'client_id' => $settings->credential('client_id'),
            'client_secret' => $settings->credential('client_secret'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Google OAuth')
                    ->description($this->statusDescription())
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->helperText('Shows "Continue with Google" on the sign-in page and every plan\'s signup form.')
                            ->columnSpanFull(),
                        TextInput::make('client_id')
                            ->label('Client ID')
                            ->helperText('Ends with .apps.googleusercontent.com'),
                        TextInput::make('client_secret')
                            ->label('Client secret')
                            ->password()->revealable(),
                        Placeholder::make('redirect_uri')
                            ->label('Authorized redirect URI')
                            ->helperText('Add this exact URL to the OAuth client in Google Cloud Console, under Authorized redirect URIs.')
                            ->content(URL::route('google.callback'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function statusDescription(): string
    {
        $settings = GoogleOauthSetting::current();

        if (! $settings->credential('client_id')) {
            return 'Not configured yet.';
        }

        return $settings->is_enabled
            ? '"Continue with Google" is live on the sign-in and signup pages.'
            : 'Configured but disabled — hidden from visitors.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => array_filter([
                'client_id' => $data['client_id'] ?? null,
                'client_secret' => $data['client_secret'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Google login settings saved')->success()->send();
    }

    public function verify(): void
    {
        $this->persist($this->form->getState());

        // Resolved AFTER persist(), not injected as a parameter on the
        // action closure below — GoogleOAuthService snapshots settings in
        // its constructor, so an eagerly-injected instance would still be
        // holding whatever was saved before this click.
        $result = app(GoogleOAuthService::class)->verifyCredentials();

        Notification::make()
            ->title($result['success'] ? 'Looks good' : 'Check your credentials')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Check credentials')
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
