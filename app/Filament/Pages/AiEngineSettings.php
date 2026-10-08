<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\AiEngineSetting;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'AI Engine (OpenAI / Claude)';

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

    public function mount(): void
    {
        $settings = AiEngineSetting::current();

        $this->form->fill([
            'text_engine' => $settings->text_engine,
            'fallback_enabled' => $settings->fallback_enabled,
            'anthropic_api_key' => null,
            'anthropic_model' => $settings->credential('anthropic_model') ?: config('ai.anthropic.model'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = AiEngineSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Which AI writes your customers\' content?')
                    ->description('Research, campaign emails, articles, scripts, replies and every other text feature use this engine. Image generation always uses OpenAI, because Claude does not create images.')
                    ->components([
                        Radio::make('text_engine')
                            ->label('Main AI engine')
                            ->options([
                                AiEngineSetting::ENGINE_OPENAI => 'OpenAI (ChatGPT models) — key set in the server .env file (OPENAI_API_KEY)',
                                AiEngineSetting::ENGINE_ANTHROPIC => 'Claude by Anthropic — uses the Claude API key below',
                            ])
                            ->required(),
                        Toggle::make('fallback_enabled')
                            ->label('If the main engine fails, automatically try the other one')
                            ->helperText('Keeps customers\' generations working during an outage or when one account runs out of credit. Only used when both engines are set up.'),
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
                    ->components([
                        Placeholder::make('openai_note')
                            ->label('')
                            ->content(filled(config('ai.openai.api_key'))
                                ? 'An OpenAI key is set in the server .env file. Text model: '.config('ai.openai.text_model').' · image model: '.config('ai.openai.image_model').'.'
                                : 'No OpenAI key is set yet. Add OPENAI_API_KEY to the server .env file (needed for images even when Claude writes the text).'),
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
        ], ['anthropic_api_key']);

        $updates = [
            'text_engine' => $data['text_engine'] === AiEngineSetting::ENGINE_ANTHROPIC ? AiEngineSetting::ENGINE_ANTHROPIC : AiEngineSetting::ENGINE_OPENAI,
            'fallback_enabled' => (bool) ($data['fallback_enabled'] ?? false),
            'credentials' => $credentials,
        ];

        if (($credentials['anthropic_api_key'] ?? null) !== ($existing['anthropic_api_key'] ?? null)) {
            $updates += ['anthropic_verified_at' => null, 'anthropic_verification_status' => null, 'anthropic_verification_message' => null];
        }

        $settings->update($updates);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->persist($data);

        $settings = AiEngineSetting::current();

        if ($settings->text_engine === AiEngineSetting::ENGINE_ANTHROPIC && ! $settings->hasAnthropic()) {
            Notification::make()->title('Saved — but Claude has no API key yet')->body('Add a Claude API key, otherwise OpenAI keeps writing the content.')->warning()->send();

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
        $settings = AiEngineSetting::current();

        if (! $settings->hasOpenAi()) {
            $result = ['success' => false, 'message' => 'OPENAI_API_KEY is not set in the server .env file.'];
        } else {
            try {
                $response = Http::withToken((string) config('ai.openai.api_key'))
                    ->baseUrl((string) config('ai.openai.base_url'))
                    ->timeout(20)->acceptJson()->get('/models');
                $result = $response->successful()
                    ? ['success' => true, 'message' => 'OpenAI key works.']
                    : ['success' => false, 'message' => $response->status() === 401 ? 'OpenAI rejected the key in .env.' : 'OpenAI returned an error ('.$response->status().').'];
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

    /**
     * @param  array{success: bool, message: string}  $result
     */
    protected function notifyResult(string $engine, array $result): void
    {
        $notification = Notification::make()->title($result['success'] ? "{$engine} is working" : "{$engine} check failed")->body($result['message']);

        $result['success'] ? $notification->success()->send() : $notification->danger()->send();
    }
}
