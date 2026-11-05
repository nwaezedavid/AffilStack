<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\HeyGenSetting;
use App\Models\VideoStudioSetting;
use App\Services\Video\HeyGenCatalog;
use App\Services\Video\HeyGenClient;
use App\Services\Video\HiggsfieldClient;
use App\Services\Video\VideoStyles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
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
        $studio = VideoStudioSetting::current();
        $styles = [];

        foreach (array_keys(VideoStyles::STYLES) as $key) {
            $styles[$key] = $studio->style($key);
        }

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            // Never the real secret — see WritesMaskedCredentials.
            'api_key' => null,
            'default_avatar_id' => $studio->heygen_default_avatar_id,
            'default_voice_id' => $studio->heygen_default_voice_id,
            'higgsfield_enabled' => $studio->higgsfield_enabled,
            'higgsfield_key_id' => null,
            'higgsfield_key_secret' => null,
            'higgsfield_webhook_secret' => null,
            'higgsfield_model' => $studio->higgsfield_model,
            'higgsfield_resolution' => $studio->higgsfield_resolution,
            'styles' => $styles,
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
                        Select::make('default_avatar_id')
                            ->label('Default presenter for one-click videos')
                            ->options(fn (): array => collect($this->safely(fn () => app(HeyGenCatalog::class)->avatars()))->mapWithKeys(fn (array $a): array => [$a['id'] => $a['name'].($a['gender'] ? ' · '.ucfirst((string) $a['gender']) : '')])->all())
                            ->placeholder('Let AffilStack pick')
                            ->searchable()
                            ->helperText('Used for "Talking review" when the customer doesn\'t pick one. The list appears once HeyGen is verified.'),
                        Select::make('default_voice_id')
                            ->label('Default voice for one-click videos')
                            ->options(fn (): array => collect($this->safely(fn () => app(HeyGenCatalog::class)->voices()))->mapWithKeys(fn (array $v): array => [$v['id'] => $v['name'].($v['language'] ? ' · '.$v['language'] : '')])->all())
                            ->placeholder('Match the presenter')
                            ->searchable(),
                    ]),
                Section::make('Higgsfield')
                    ->description($this->higgsfieldStatus())
                    ->columns(2)
                    ->components([
                        Toggle::make('higgsfield_enabled')
                            ->label('Use Higgsfield for product videos')
                            ->helperText('Renders the "Product showcase" and "Problem → solution" styles: short clips with the product itself on screen.')
                            ->columnSpanFull(),
                        TextInput::make('higgsfield_key_id')
                            ->label('API Key ID')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled(VideoStudioSetting::current()->higgsfieldKeyId())))
                            ->helperText('From console.higgsfield.ai → API keys → Create key. This is the developer API, separate from a Higgsfield website plan.'),
                        TextInput::make('higgsfield_key_secret')
                            ->label('API Key Secret')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled(VideoStudioSetting::current()->higgsfieldKeySecret())))
                            ->helperText('Shown once when you create the key.'),
                        TextInput::make('higgsfield_webhook_secret')
                            ->label('Webhook signing secret (optional)')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled(VideoStudioSetting::current()->higgsfieldWebhookSecret())))
                            ->helperText('Starts with whsec_. With it, finished videos arrive instantly instead of within a minute. Webhook address: '.route('webhooks.higgsfield'))
                            ->columnSpanFull(),
                        TextInput::make('higgsfield_model')
                            ->label('Video model')
                            ->placeholder('bytedance/seedance-2.5/reference-to-video')
                            ->helperText('The model path from Higgsfield\'s docs. Default: bytedance/seedance-2.5/reference-to-video (builds the clip from the product photo, with sound).'),
                        Select::make('higgsfield_resolution')
                            ->label('Resolution')
                            ->options(['480p' => '480p (cheapest)', '720p' => '720p (recommended)', '1080p' => '1080p (most expensive)'])
                            ->placeholder('720p (recommended)'),
                    ]),
                Section::make('One-click video styles')
                    ->description('What customers choose from on each product\'s UGC video page. Credits are charged only when the video is ready. Use "Estimate Higgsfield cost" to check your cost per video before setting prices.')
                    ->components(collect(VideoStyles::STYLES)->map(fn (array $style, string $key) => Grid::make(3)->components([
                        Toggle::make("styles.{$key}.enabled")
                            ->label($style['label'].' ('.($style['provider'] === VideoStyles::HEYGEN ? 'HeyGen' : 'Higgsfield').')')
                            ->inline(false),
                        TextInput::make("styles.{$key}.credits")
                            ->label('Credits per video')
                            ->numeric()->minValue(1),
                        $style['provider'] === VideoStyles::HEYGEN
                            ? TextInput::make("styles.{$key}.duration")->label('Length (seconds, approx.)')->numeric()->minValue(20)->maxValue(70)
                            : Select::make("styles.{$key}.duration")->label('Length')->options([5 => '5 seconds', 8 => '8 seconds', 10 => '10 seconds', 12 => '12 seconds', 15 => '15 seconds', 20 => '20 seconds', 30 => '30 seconds'])->selectablePlaceholder(false),
                    ]))->values()->all()),
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

        $studio = VideoStudioSetting::current();
        $oldKeys = [$studio->higgsfieldKeyId(), $studio->higgsfieldKeySecret(), $studio->higgsfield_model];
        $styles = [];

        foreach (VideoStyles::STYLES as $key => $style) {
            $styles[$key] = [
                'enabled' => (bool) data_get($data, "styles.{$key}.enabled", true),
                'credits' => max(1, (int) data_get($data, "styles.{$key}.credits", $style['credits'])),
                'duration' => (int) data_get($data, "styles.{$key}.duration", $style['duration']),
            ];
        }

        $studio->update([
            'higgsfield_enabled' => (bool) ($data['higgsfield_enabled'] ?? false),
            'higgsfield_model' => trim((string) ($data['higgsfield_model'] ?? '')) ?: 'bytedance/seedance-2.5/reference-to-video',
            'higgsfield_resolution' => in_array($data['higgsfield_resolution'] ?? null, ['480p', '720p', '1080p'], true) ? $data['higgsfield_resolution'] : '720p',
            'heygen_default_avatar_id' => $data['default_avatar_id'] ?? null,
            'heygen_default_voice_id' => $data['default_voice_id'] ?? null,
            'styles' => $styles,
            'credentials' => $this->mergeMaskedCredentials($studio->credentials ?? [], [
                'key_id' => $data['higgsfield_key_id'] ?? null,
                'key_secret' => $data['higgsfield_key_secret'] ?? null,
                'webhook_secret' => $data['higgsfield_webhook_secret'] ?? null,
            ], ['key_id', 'key_secret', 'webhook_secret']),
        ]);

        $studio->refresh();

        // A new key or model needs a fresh check before customers can use it.
        if ($oldKeys !== [$studio->higgsfieldKeyId(), $studio->higgsfieldKeySecret(), $studio->higgsfield_model]) {
            $studio->update(['higgsfield_verification_status' => null, 'higgsfield_verification_message' => null, 'higgsfield_verified_at' => null]);
        }
    }

    /**
     * The presenter lists come from HeyGen; a HeyGen outage must never
     * stop this page from loading.
     *
     * @param  callable(): array<int, array<string, mixed>>  $load
     * @return array<int, array<string, mixed>>
     */
    protected function safely(callable $load): array
    {
        try {
            return $load();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function higgsfieldStatus(): string
    {
        $studio = VideoStudioSetting::current();

        if (! $studio->higgsfield_verified_at) {
            return 'Not verified yet. Paste the key, save, then press "Verify Higgsfield".';
        }

        $when = $studio->higgsfield_verified_at->diffForHumans();

        return $studio->higgsfield_verification_status === 'success'
            ? "✓ Verified {$when} — {$studio->higgsfield_verification_message}"
            : "✗ Verification failed {$when} — {$studio->higgsfield_verification_message}";
    }

    /**
     * Checks the key with Higgsfield's price estimate for a "Product
     * showcase" video, which also tells the admin what one costs.
     */
    public function verifyHiggsfield(): void
    {
        $this->persist($this->form->getState());
        $studio = VideoStudioSetting::current();

        if (! $studio->higgsfieldKeyId() || ! $studio->higgsfieldKeySecret()) {
            Notification::make()->title('Paste the Higgsfield Key ID and Secret first')->warning()->send();

            return;
        }

        $result = (new HiggsfieldClient($studio->higgsfieldKeyId(), $studio->higgsfieldKeySecret()))
            ->estimate($studio->higgsfield_model, $this->sampleParams($studio, 'product_showcase'));

        $studio->update([
            'higgsfield_verified_at' => now(),
            'higgsfield_verification_status' => $result['success'] ? 'success' : 'failed',
            'higgsfield_verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'Higgsfield connected' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    public function estimateHiggsfield(): void
    {
        $studio = VideoStudioSetting::current();

        if (! $studio->higgsfieldReady()) {
            Notification::make()->title('Verify Higgsfield first')->warning()->send();

            return;
        }

        $client = new HiggsfieldClient((string) $studio->higgsfieldKeyId(), (string) $studio->higgsfieldKeySecret());
        $lines = [];

        foreach (VideoStyles::STYLES as $key => $style) {
            if ($style['provider'] !== VideoStyles::HIGGSFIELD) {
                continue;
            }

            $result = $client->estimate($studio->higgsfield_model, $this->sampleParams($studio, $key));
            $config = $studio->style($key);
            $lines[] = $style['label'].' ('.$config['duration'].'s, '.$studio->higgsfield_resolution.'): '
                .($result['usd'] !== null ? 'US$'.number_format($result['usd'], 2).' per video, priced at '.$config['credits'].' credits' : $result['message']);
        }

        Notification::make()->title('Higgsfield cost per video')->body(implode("\n", $lines))->info()->persistent()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleParams(VideoStudioSetting $studio, string $style): array
    {
        return [
            'prompt' => 'Vertical UGC-style smartphone video ad of a product on a kitchen table, natural voiceover.',
            'image_urls' => [asset('images/features/api-hub.webp')],
            'duration' => $studio->style($style)['duration'],
            'resolution' => $studio->higgsfield_resolution ?: '720p',
            'aspect_ratio' => '9:16',
            'generate_audio' => true,
        ];
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Video settings saved')->success()->send();
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
                ->label('Verify HeyGen')
                ->color('gray')
                ->action(fn () => $this->verify()),
            Action::make('verifyHiggsfield')
                ->label('Verify Higgsfield')
                ->color('gray')
                ->action(fn () => $this->verifyHiggsfield()),
            Action::make('estimateHiggsfield')
                ->label('Estimate Higgsfield cost')
                ->color('gray')
                ->action(fn () => $this->estimateHiggsfield()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
