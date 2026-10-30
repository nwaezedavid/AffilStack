<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\LeadSourceSetting;
use App\Services\Leads\ApolloClient;
use App\Services\Leads\HunterClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Site > Lead sources: the paid data providers behind B2B lead finding.
 * Google Maps uses the Google Places key from the server settings; Apollo
 * and Hunter are optional extras switched on here.
 */
class LeadSourceSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Lead sources';

    protected static ?string $title = 'Lead sources (Apollo & Hunter)';

    protected string $view = 'filament.pages.lead-source-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = LeadSourceSetting::current();

        $this->form->fill([
            'apollo_enabled' => $settings->apollo_enabled,
            'apollo_api_key' => null,
            'hunter_enabled' => $settings->hunter_enabled,
            'hunter_api_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = LeadSourceSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Google Maps')
                    ->description(filled(config('services.google_places.api_key'))
                        ? 'Connected. Finds local businesses and reads the contact emails on their websites.'
                        : 'Not connected: add GOOGLE_PLACES_API_KEY to the server settings (see Integration guides).'),
                Section::make('Apollo')
                    ->description($this->statusLine('Finds decision-makers by job title, with business emails. Each email found uses one Apollo credit.', $settings->apollo_test_status, $settings->apollo_test_message))
                    ->components([
                        Toggle::make('apollo_enabled')->label('Use Apollo for lead finding'),
                        TextInput::make('apollo_api_key')
                            ->label('Apollo master API key')
                            ->password()
                            ->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->apolloKey())))
                            ->helperText('In Apollo: Settings → Integrations → API → Create new key, and switch on "Set as master key". People search needs a master key.'),
                    ]),
                Section::make('Hunter')
                    ->description($this->statusLine('Finds business emails for companies whose website lists none, and verifies every lead email.', $settings->hunter_test_status, $settings->hunter_test_message))
                    ->components([
                        Toggle::make('hunter_enabled')->label('Use Hunter for lead finding'),
                        TextInput::make('hunter_api_key')
                            ->label('Hunter API key')
                            ->password()
                            ->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->hunterKey())))
                            ->helperText('In Hunter: your avatar → API → copy the secret key.'),
                    ]),
            ]);
    }

    protected function statusLine(string $what, ?string $status, ?string $message): string
    {
        return $what.match ($status) {
            'success' => ' Last test: working.',
            'failed' => ' Last test failed: '.$message,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): LeadSourceSetting
    {
        $settings = LeadSourceSetting::current();

        $settings->update([
            'apollo_enabled' => (bool) ($data['apollo_enabled'] ?? false),
            'hunter_enabled' => (bool) ($data['hunter_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'apollo_api_key' => $data['apollo_api_key'] ?? null,
                'hunter_api_key' => $data['hunter_api_key'] ?? null,
            ], ['apollo_api_key', 'hunter_api_key']),
        ]);

        return $settings->fresh();
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Lead sources saved')->success()->send();
    }

    public function testApollo(): void
    {
        $settings = $this->persist($this->form->getState());

        $this->runTest('apollo', $settings->apolloKey(), fn (string $key): string => (new ApolloClient($key))->verify());
    }

    public function testHunter(): void
    {
        $settings = $this->persist($this->form->getState());

        $this->runTest('hunter', $settings->hunterKey(), fn (string $key): string => (new HunterClient($key))->verifyKey());
    }

    /**
     * @param  callable(string): string  $test
     */
    protected function runTest(string $provider, ?string $key, callable $test): void
    {
        $settings = LeadSourceSetting::current();

        if (! $key) {
            Notification::make()->title('Paste the API key first')->warning()->send();

            return;
        }

        try {
            $message = $test($key);
            $settings->update(["{$provider}_test_status" => 'success', "{$provider}_test_message" => $message]);
            Notification::make()->title('Connected')->body($message)->success()->send();
        } catch (Throwable $e) {
            $settings->update(["{$provider}_test_status" => 'failed', "{$provider}_test_message" => $e->getMessage()]);
            Notification::make()->title('Test failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testApollo')->label('Test Apollo')->color('gray')->action(fn () => $this->testApollo()),
            Action::make('testHunter')->label('Test Hunter')->color('gray')->action(fn () => $this->testHunter()),
        ];
    }
}
