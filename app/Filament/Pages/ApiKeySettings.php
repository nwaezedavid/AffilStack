<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\ApiKeySetting;
use App\Services\Leads\GoogleMapsLeadService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Site > API keys & links: the keys and links that used to need editing the
 * server .env file. Saved values (encrypted) take over from the .env ones;
 * every other integration already has its own settings page.
 */
class ApiKeySettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'API keys & links';

    protected static ?string $title = 'API keys & links';

    protected string $view = 'filament.pages.api-key-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = ApiKeySetting::current();

        $this->form->fill([
            'google_places_api_key' => null,
            'chrome_extension_store_url' => $settings->chrome_extension_store_url,
            'zapier_invite_url' => $settings->zapier_invite_url,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = ApiKeySetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Where is everything else?')
                    ->description('Each integration has its own page: AI Engine (OpenAI, Claude, Gemini), Payment gateways, Platform Email, Google Login, LinkedIn, TikTok, Instagram, Lead sources (Apollo, Hunter), UGC Video Settings (HeyGen, Higgsfield), Bing Webmaster and GitHub Sync. This page holds the last few that used to live only in the server settings file.'),
                Section::make('Google Places (Local Leads and lead finding)')
                    ->description(filled(config('services.google_places.api_key')) ? 'A key is active.' : 'No key set yet — Local Leads and Google Maps lead finding are off.')
                    ->components([
                        TextInput::make('google_places_api_key')
                            ->label('Google Places API key')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('google_places_api_key'))))
                            ->helperText('Google Cloud Console → APIs & Services → Credentials. Enable "Places API (New)" and restrict the key to it. See Integration guides → Google Places.'),
                    ]),
                Section::make('Chrome Web Store')
                    ->components([
                        TextInput::make('chrome_extension_store_url')
                            ->label('Extension store link')
                            ->url()
                            ->rule('starts_with:https://chromewebstore.google.com/,https://chrome.google.com/webstore/')
                            ->placeholder('https://chromewebstore.google.com/detail/…')
                            ->helperText('After Google publishes the extension, paste its store link: customers then get an "Add to Chrome" button instead of the zip download.'),
                    ]),
                Section::make('Zapier')
                    ->components([
                        TextInput::make('zapier_invite_url')
                            ->label('Zapier invite link')
                            ->url()
                            ->rule('starts_with:https://zapier.com/')
                            ->placeholder('https://zapier.com/developer/public-invite/…')
                            ->helperText('From the Zapier developer platform once the AffilStack integration is pushed. Shown on customers\' API Access page.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = ApiKeySetting::current();

        $settings->update([
            'chrome_extension_store_url' => $data['chrome_extension_store_url'] ?: null,
            'zapier_invite_url' => $data['zapier_invite_url'] ?: null,
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'google_places_api_key' => $data['google_places_api_key'] ?? null,
            ], ['google_places_api_key']),
        ]);

        ApiKeySetting::applyToConfig();

        Notification::make()->title('API keys & links saved')->success()->send();
    }

    public function testPlaces(): void
    {
        $this->save();
        $key = ApiKeySetting::current()->credential('google_places_api_key') ?: config('services.google_places.api_key');

        if (! $key) {
            Notification::make()->title('Paste the Google Places API key first')->warning()->send();

            return;
        }

        try {
            $results = (new GoogleMapsLeadService((string) $key))->search('coffee shop', 'Lagos, Nigeria');
            Notification::make()->title('Google Places works')->body('A test search found '.count($results).' businesses.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Google Places test failed')->body($e->getMessage().' Check the key is enabled for "Places API (New)" and billing is on.')->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testPlaces')->label('Test Google Places')->color('gray')->action(fn () => $this->testPlaces()),
        ];
    }
}
