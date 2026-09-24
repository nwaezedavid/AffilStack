<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\GoogleOauthSetting;
use App\Services\Auth\GoogleOAuthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
 * Admin control for "Continue with Google" (task #84): enable/disable,
 * enter the OAuth client id/secret (stored encrypted via
 * GoogleOauthSetting), and check they're at least correctly formatted.
 */
class GoogleOAuthSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

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
            'gmail_sending_enabled' => $settings->gmail_sending_enabled,
            'youtube_publishing_enabled' => $settings->youtube_publishing_enabled,
            'youtube_approval_status' => $settings->youtube_approval_status,
            'youtube_approval_notes' => $settings->youtube_approval_notes,
            'client_id' => $settings->credential('client_id'),
            // Never the real secret — see WritesMaskedCredentials.
            'client_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = GoogleOauthSetting::current();

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
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('client_secret')))),
                        Placeholder::make('redirect_uri')
                            ->label('Authorized redirect URI')
                            ->helperText('Add this exact URL to the OAuth client in Google Cloud Console, under Authorized redirect URIs.')
                            ->content(URL::route('google.callback'))
                            ->columnSpanFull(),
                    ]),
                Section::make('Gmail sending (CRM nurture emails)')
                    ->description('Lets a user connect their own Gmail account so their CRM nurture emails send from their address instead of AffilStack\'s — protects our shared sending domain\'s reputation. Uses the same client id/secret above, requesting the additional gmail.send scope. Google treats gmail.send as a restricted scope: it requires this OAuth consent screen to pass Google\'s security assessment before it works for anyone outside your own test users, even if "Continue with Google" above already works.')
                    ->columns(2)
                    ->components([
                        Toggle::make('gmail_sending_enabled')
                            ->label('Allow users to connect Gmail for sending')
                            ->columnSpanFull(),
                        Placeholder::make('gmail_redirect_uri')
                            ->label('Additional authorized redirect URI')
                            ->helperText('Add this one too — it\'s separate from the sign-in redirect above.')
                            ->content(URL::route('email-connections.gmail.callback'))
                            ->columnSpanFull(),
                    ]),
                Section::make('YouTube publishing (UGC videos)')
                    ->description('Lets a user connect their YouTube channel so AffilStack can publish a rendered UGC video for them. Uses the same client id/secret above, requesting the additional youtube.upload scope. The YouTube Data API requires a separate Audit + Quota Extension before uploads work for real users — track that below. Until it\'s approved, publishing falls back to "download and post manually" even for a connected channel.')
                    ->columns(2)
                    ->components([
                        Toggle::make('youtube_publishing_enabled')
                            ->label('Allow users to connect YouTube')
                            ->columnSpanFull(),
                        Select::make('youtube_approval_status')
                            ->label('Audit + Quota Extension status')
                            ->options([
                                'not_submitted' => 'Not submitted yet',
                                'pending' => 'Submitted — awaiting review',
                                'approved' => 'Approved',
                            ])
                            ->native(false),
                        Placeholder::make('youtube_redirect_uri')
                            ->label('Additional authorized redirect URI')
                            ->helperText('Add this one too.')
                            ->content(URL::route('social-connections.callback', ['provider' => 'youtube'])),
                        Textarea::make('youtube_approval_notes')
                            ->label('Notes')
                            ->rows(2)
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
        $settings = GoogleOauthSetting::current();

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'gmail_sending_enabled' => (bool) ($data['gmail_sending_enabled'] ?? false),
            'youtube_publishing_enabled' => (bool) ($data['youtube_publishing_enabled'] ?? false),
            'youtube_approval_status' => $data['youtube_approval_status'] ?? 'not_submitted',
            'youtube_approval_notes' => $data['youtube_approval_notes'] ?? null,
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'client_id' => $data['client_id'] ?? null,
                'client_secret' => $data['client_secret'] ?? null,
            ], ['client_secret']),
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
