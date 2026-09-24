<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\HeyGenSetting;
use App\Services\Video\HeyGenClient;
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
 * Admin control for the UGC video overhaul: connect and verify
 * AffilStack's own HeyGen API key (from app.heygen.com/settings/api) so
 * every user's "Generate video" click renders through this one account —
 * users spend platform credits (config credits.ugc_video), never see or
 * need a HeyGen account of their own. See HeyGenSetting/HeyGenClient and
 * UgcVideoService.
 */
class UgcVideoSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'UGC Video Settings';

    protected static ?string $title = 'UGC Video Settings';

    protected string $view = 'filament.pages.ugc-video-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = HeyGenSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            // Never the real secret — see WritesMaskedCredentials.
            'api_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = HeyGenSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('HeyGen')
                    ->description($this->statusDescription())
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Users can generate UGC videos')
                            ->helperText('While off, users can still draft UGC scripts, but "Generate video" stays disabled.')
                            ->columnSpanFull(),
                        TextInput::make('api_key')
                            ->label('HeyGen API key')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('api_key'))))
                            ->helperText('From app.heygen.com/settings/api — this is AffilStack\'s own account, billed to you and recovered through user credits.'),
                    ]),
            ]);
    }

    protected function statusDescription(): string
    {
        $settings = HeyGenSetting::current();

        if (! $settings->verified_at) {
            return 'Not verified yet.';
        }

        $when = $settings->verified_at->diffForHumans();

        return $settings->verification_status === 'success'
            ? "✓ Verified {$when} — {$settings->verification_message}"
            : "✗ Verification failed {$when} — {$settings->verification_message}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = HeyGenSetting::current();

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'api_key' => $data['api_key'] ?? null,
            ], ['api_key']),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('UGC video settings saved')->success()->send();
    }

    public function verify(): void
    {
        $this->persist($this->form->getState());
        $settings = HeyGenSetting::current();

        $result = (new HeyGenClient((string) $settings->credential('api_key')))->verifyApiKey();

        $settings->update([
            'verified_at' => now(),
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'HeyGen connected' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify connection')
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
