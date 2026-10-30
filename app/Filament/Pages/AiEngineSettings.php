<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\AiEngineSetting;
use App\Services\AI\OpenAIProvider;
use App\Services\AI\RoutingAIProvider;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Choose which AI writes AffilStack's text — OpenAI or Claude — with the
 * other as an automatic fallback. Images always use OpenAI.
 */
class AiEngineSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'ai_agents';

    /**
     * Super-admin only: whoever controls the AI key decides where every
     * customer prompt and generation is sent.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'AI Engine (OpenAI / Claude / Gemini)';

    protected static ?int $navigationSort = -10;

    protected static ?string $title = 'AI Engine';

    protected string $view = 'filament.pages.ai-engine-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Claude models offered in the dropdown (October 2026 line-up).
     *
     * @var array<string, string>
     */
    public const CLAUDE_MODELS = [
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — best balance of quality, speed and cost (recommended)',
        'claude-opus-5-5' => 'Claude Opus 5.5 — highest quality, about 2× the cost of Sonnet',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 — fastest and cheapest, simpler writing',
    ];

    public const GEMINI_MODELS = [
        'gemini-3.8-flash' => 'Gemini 3.8 Flash — fast, low cost, strong writing (recommended)',
        'gemini-4-argon' => 'Gemini 4 Argon — Google\'s most capable model, higher cost',
        'gemini-3.6-flash' => 'Gemini 3.6 Flash — previous Flash generation',
        'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite — cheapest, simpler writing',
    ];

    public const ENGINE_LABELS = [
        AiEngineSetting::ENGINE_OPENAI => 'OpenAI (ChatGPT models)',
        AiEngineSetting::ENGINE_ANTHROPIC => 'Claude by Anthropic',
        AiEngineSetting::ENGINE_GEMINI => 'Gemini by Google',
    ];

    public function mount(): void
    {
        $settings = AiEngineSetting::current();

        $this->form->fill([
            'text_engine' => $settings->text_engine,
            'second_engine' => $settings->second_engine,
            'fallback_enabled' => $settings->fallback_enabled,
            'anthropic_api_key' => null,
            'anthropic_model' => $settings->credential('anthropic_model') ?: config('ai.anthropic.model'),
            'openai_api_key' => null,
            'openai_text_model' => $settings->openaiTextModel(),
            'openai_image_model' => $settings->openaiImageModel(),
            'gemini_api_key' => null,
            'gemini_model' => $settings->credential('gemini_model') ?: config('ai.gemini.model'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = AiEngineSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Which AI writes your customers\' content?')
                    ->description('Research, campaign emails, articles, scripts, replies and every other text feature use these engines. If the main one fails (an outage, an empty account, a rejected key), AffilStack moves on to the next one, and then the last, so customers\' work keeps going. Images always use OpenAI.')
                    ->columns(2)
                    ->components([
                        Radio::make('text_engine')
                            ->label('Main AI engine')
                            ->options(self::ENGINE_LABELS)
                            ->live()
                            ->required(),
                        Select::make('second_engine')
                            ->label('If it fails, try this one next')
                            ->options(fn (Get $get): array => collect(self::ENGINE_LABELS)->except($get('text_engine'))->all())
                            ->placeholder('Automatic')
                            ->helperText('The remaining engine is tried last. Engines without an API key are skipped.'),
                        Toggle::make('fallback_enabled')
                            ->label('Switch to the next engine automatically when one fails')
                            ->helperText('Off: only the main engine is used.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Claude (Anthropic API)')
                    ->description($this->status('anthropic'))
                    ->columns(2)
                    ->components([
                        TextInput::make('anthropic_api_key')
                            ->label('Claude API key')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('anthropic_api_key'))))
                            ->helperText('Create it at console.anthropic.com → API Keys (starts with "sk-ant-"). Leave empty to keep the saved key; if none is saved here, the ANTHROPIC_API_KEY in .env or the key saved for Brain is used.'),
                        Select::make('anthropic_model')
                            ->label('Claude model')
                            ->options(self::CLAUDE_MODELS)
                            ->required(),
                        Placeholder::make('subscription_note')
                            ->label('Can I use my Claude Pro/Max subscription instead?')
                            ->content('No. A Claude.ai subscription (Pro, Max, Team) only covers your own use of Claude — the Claude apps and Claude Code. Anthropic\'s terms don\'t allow a subscription login to power another app or website, so AffilStack must use a pay-as-you-go API key from console.anthropic.com. You can keep using your subscription yourself to plan content or manage AffilStack.')
                            ->columnSpanFull(),
                    ]),

                Section::make('OpenAI')
                    ->description($this->status('openai'))
                    ->columns(2)
                    ->components([
                        TextInput::make('openai_api_key')
                            ->label('OpenAI API key')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('openai_api_key')) || filled(config('ai.openai.api_key'))))
                            ->helperText('Create it at platform.openai.com → API keys (starts with "sk-"). Needed for images even when another engine writes the text. Leave empty to keep the current key (saved here, or OPENAI_API_KEY in the server .env).')
                            ->columnSpanFull(),
                        TextInput::make('openai_text_model')
                            ->label('Text model')
                            ->helperText('For example gpt-4o-mini.')
                            ->required(),
                        TextInput::make('openai_image_model')
                            ->label('Image model')
                            ->helperText('For example gpt-image-2.')
                            ->required(),
                    ]),

                Section::make('Gemini (Google)')
                    ->description($this->status('gemini'))
                    ->columns(2)
                    ->components([
                        TextInput::make('gemini_api_key')
                            ->label('Gemini API key')
                            ->password()->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('gemini_api_key')) || filled(config('ai.gemini.api_key'))))
                            ->helperText('Create it at aistudio.google.com → Get API key. Leave empty to keep the saved key.'),
                        Select::make('gemini_model')
                            ->label('Gemini model')
                            ->options(self::GEMINI_MODELS)
                            ->required(),
                    ]),
            ]);
    }

    protected function status(string $engine): string
    {
        $settings = AiEngineSetting::current();
        $at = $settings->{$engine.'_verified_at'};

        if (! $at) {
            return 'Not checked yet — press "Test" below after saving.';
        }

        $prefix = $settings->{$engine.'_verification_status'} === 'success' ? '✓ Working' : '✗ Check failed';

        return "{$prefix} (checked {$at->diffForHumans()}) — {$settings->{$engine.'_verification_message'}}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = AiEngineSetting::current();
        $existing = $settings->credentials ?? [];

        $credentials = $this->mergeMaskedCredentials($existing, [
            'anthropic_api_key' => $data['anthropic_api_key'] ?? null,
            'anthropic_model' => $data['anthropic_model'] ?? null,
            'openai_api_key' => $data['openai_api_key'] ?? null,
            'openai_text_model' => trim((string) ($data['openai_text_model'] ?? '')) ?: null,
            'openai_image_model' => trim((string) ($data['openai_image_model'] ?? '')) ?: null,
            'gemini_api_key' => $data['gemini_api_key'] ?? null,
            'gemini_model' => $data['gemini_model'] ?? null,
        ], ['anthropic_api_key', 'openai_api_key', 'gemini_api_key']);

        $main = in_array($data['text_engine'] ?? null, AiEngineSetting::ENGINES, true) ? $data['text_engine'] : AiEngineSetting::ENGINE_OPENAI;
        $second = in_array($data['second_engine'] ?? null, AiEngineSetting::ENGINES, true) && $data['second_engine'] !== $main ? $data['second_engine'] : null;

        $updates = [
            'text_engine' => $main,
            'second_engine' => $second,
            'fallback_enabled' => (bool) ($data['fallback_enabled'] ?? false),
            'credentials' => $credentials,
        ];

        foreach (['anthropic', 'openai', 'gemini'] as $engine) {
            if (($credentials[$engine.'_api_key'] ?? null) !== ($existing[$engine.'_api_key'] ?? null)) {
                $updates += [$engine.'_verified_at' => null, $engine.'_verification_status' => null, $engine.'_verification_message' => null];
            }
        }

        // The OpenAI client is built once per request; rebuild it with the new key.
        app()->forgetInstance(OpenAIProvider::class);

        $settings->update($updates);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->persist($data);

        $settings = AiEngineSetting::current();

        if (! $settings->hasEngine($settings->text_engine)) {
            Notification::make()->title('Saved — but '.self::ENGINE_LABELS[$settings->text_engine].' has no API key yet')->body('Add its API key, otherwise the next engine that has one writes the content.')->warning()->send();

            return;
        }

        Notification::make()->title('AI engine settings saved')->success()->send();
    }

    public function testAnthropic(): void
    {
        $this->persist($this->form->getState());
        $settings = AiEngineSetting::current();

        $result = $settings->hasAnthropic()
            ? RoutingAIProvider::anthropicFor($settings)->verify()
            : ['success' => false, 'message' => 'No Claude API key is saved yet.'];

        $settings->update([
            'anthropic_verified_at' => now(),
            'anthropic_verification_status' => $result['success'] ? 'success' : 'failed',
            'anthropic_verification_message' => $result['message'],
        ]);

        $this->notifyResult('Claude', $result);
    }

    public function testOpenAi(): void
    {
        $this->persist($this->form->getState());
        $settings = AiEngineSetting::current();

        if (! $settings->hasOpenAi()) {
            $result = ['success' => false, 'message' => 'No OpenAI API key is saved yet.'];
        } else {
            try {
                $response = Http::withToken($settings->openaiApiKey())
                    ->baseUrl((string) config('ai.openai.base_url'))
                    ->timeout(20)->acceptJson()->get('/models');
                $result = $response->successful()
                    ? ['success' => true, 'message' => 'OpenAI key works. Text model: '.$settings->openaiTextModel().' · image model: '.$settings->openaiImageModel().'.']
                    : ['success' => false, 'message' => $response->status() === 401 ? 'OpenAI rejected this API key. Create a new one at platform.openai.com → API keys.' : 'OpenAI returned an error ('.$response->status().').'];
            } catch (Throwable $e) {
                $result = ['success' => false, 'message' => 'Could not reach OpenAI: '.$e->getMessage()];
            }
        }

        $settings->update([
            'openai_verified_at' => now(),
            'openai_verification_status' => $result['success'] ? 'success' : 'failed',
            'openai_verification_message' => $result['message'],
        ]);

        $this->notifyResult('OpenAI', $result);
    }

    public function testGemini(): void
    {
        $this->persist($this->form->getState());
        $settings = AiEngineSetting::current();

        $result = $settings->hasGemini()
            ? RoutingAIProvider::geminiFor($settings)->verify()
            : ['success' => false, 'message' => 'No Gemini API key is saved yet.'];

        $settings->update([
            'gemini_verified_at' => now(),
            'gemini_verification_status' => $result['success'] ? 'success' : 'failed',
            'gemini_verification_message' => $result['message'],
        ]);

        $this->notifyResult('Gemini', $result);
    }

    /**
     * @param  array{success: bool, message: string}  $result
     */
    protected function notifyResult(string $engine, array $result): void
    {
        $notification = Notification::make()->title($result['success'] ? "{$engine} is working" : "{$engine} check failed")->body($result['message']);

        $result['success'] ? $notification->success()->send() : $notification->danger()->send();
    }
}
