<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\LinkedInOauthSetting;
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
 * Admin control for "Connect LinkedIn" (task #3) — enable/disable and the
 * OAuth client id/secret for a self-serve "Sign In with LinkedIn using
 * OpenID Connect" app. See App\Services\Social\LinkedInOAuthService for
 * why this never requests a posting scope.
 */
class LinkedInOAuthSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'LinkedIn Connect';

    protected static ?string $title = 'LinkedIn Connect';

    protected string $view = 'filament.pages.linkedin-oauth-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = LinkedInOauthSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'client_id' => $settings->credential('client_id'),
            // Never the real secret — see WritesMaskedCredentials.
            'client_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = LinkedInOauthSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('LinkedIn OAuth')
                    ->description('Lets a user connect their LinkedIn identity so exported content is tied to a real, named account — see the Social Connections page. This never requests permission to post or message on anyone\'s behalf; only "Sign In with LinkedIn using OpenID Connect" (self-serve, no partner review needed).')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->helperText('Shows "Connect LinkedIn" on every user\'s Social Connections page.')
                            ->columnSpanFull(),
                        TextInput::make('client_id')->label('Client ID'),
                        TextInput::make('client_secret')
                            ->label('Client secret')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('client_secret')))),
                        Placeholder::make('redirect_uri')
                            ->label('Authorized redirect URL')
                            ->helperText('Add this exact URL to the app in the LinkedIn Developer Portal, under OAuth 2.0 settings.')
                            ->content(URL::route('social-connections.callback', ['provider' => 'linkedin']))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = LinkedInOauthSetting::current();

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'client_id' => $data['client_id'] ?? null,
                'client_secret' => $data['client_secret'] ?? null,
            ], ['client_secret']),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('LinkedIn connect settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
