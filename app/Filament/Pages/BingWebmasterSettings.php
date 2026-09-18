<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\BingWebmasterSetting;
use App\Services\Analytics\BingWebmasterClient;
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
 * Bing Webmaster Tools — guided connect (see BingWebmasterSetting for why
 * this isn't a full auto-provisioning flow like Google's): paste an API
 * key, add the site on bing.com/webmasters with meta-tag verification,
 * paste the code back here, then verify + auto-submit the sitemap.
 */
class BingWebmasterSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Bing Webmaster';

    protected static ?string $title = 'Bing Webmaster Tools';

    protected string $view = 'filament.pages.bing-webmaster-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = BingWebmasterSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'api_key' => $settings->credential('api_key'),
            'verification_code' => $settings->verification_code,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Bing Webmaster Tools')
                    ->description($this->statusDescription())
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->helperText('Injects the verification tag and allows sitemap submission once configured.'),
                        TextInput::make('api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->helperText('From bing.com/webmasters — Settings (gear icon) > API Access.'),
                        TextInput::make('verification_code')
                            ->label('Site verification code')
                            ->helperText('On bing.com/webmasters, add this site and choose the "Meta Tag" verification option — paste just the "content" value here, not the full tag.'),
                    ]),
            ]);
    }

    protected function statusDescription(): string
    {
        $settings = BingWebmasterSetting::current();

        if (! $settings->isConfigured()) {
            return 'Not configured yet.';
        }

        return $settings->verification_status === 'success'
            ? 'Verified and receiving sitemap submissions.'
            : 'Configured — verify below once the site is added on Bing and live.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        BingWebmasterSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'verification_code' => $data['verification_code'] ?? null,
            'credentials' => array_filter([
                'api_key' => $data['api_key'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Bing Webmaster settings saved')->success()->send();
    }

    public function verify(): void
    {
        $this->persist($this->form->getState());
        $settings = BingWebmasterSetting::current();

        $result = (new BingWebmasterClient((string) $settings->credential('api_key')))->verifySite(rtrim(url('/'), '/'));

        $settings->update([
            'verified_at' => now(),
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_message' => $result['message'],
        ]);

        if ($result['success']) {
            $sitemap = (new BingWebmasterClient((string) $settings->credential('api_key')))
                ->submitSitemap(rtrim(url('/'), '/'), rtrim(url('/'), '/').'/sitemap.xml');

            $result['message'] .= ' '.$sitemap['message'];
        }

        Notification::make()
            ->title($result['success'] ? 'Verified' : 'Not verified yet')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'warning')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify & submit sitemap')
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
