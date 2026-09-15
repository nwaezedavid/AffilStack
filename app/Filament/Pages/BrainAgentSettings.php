<?php

namespace App\Filament\Pages;

use App\Models\BrainAgentSetting;
use App\Services\AI\AnthropicClient;
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

/**
 * Admin control panel for Brain (the Marketing Agent): connect the admin's
 * own Anthropic (Claude) API key — used for drafting and for every
 * agentic Meta Ads action — and their own Meta Ads MCP server (set up
 * outside AffilStack, using their own Meta App credentials). Both are
 * verified against the live API before Brain is allowed to launch or
 * optimize a real campaign — see BrainAgentSetting::canLaunchLiveCampaigns().
 * Drafting a campaign only needs the Anthropic connection; nothing here
 * requires writing any code.
 */
class BrainAgentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Brain Settings';

    protected static ?string $title = 'Brain — Marketing Agent Settings';

    protected string $view = 'filament.pages.brain-agent-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = BrainAgentSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'anthropic_api_key' => $settings->credential('anthropic_api_key'),
            'anthropic_model' => $settings->credential('anthropic_model'),
            'meta_mcp_url' => $settings->credential('meta_mcp_url'),
            'meta_mcp_token' => $settings->credential('meta_mcp_token'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('Brain can launch and optimize live campaigns')
                    ->helperText('While off, Brain can still draft campaigns for review, but "Approve & Launch" stays disabled.')
                    ->columnSpanFull(),

                Section::make('Claude (Anthropic)')
                    ->description($this->anthropicStatusDescription())
                    ->columns(2)
                    ->components([
                        TextInput::make('anthropic_api_key')
                            ->label('Anthropic API key')
                            ->password()->revealable()
                            ->helperText('From console.anthropic.com — this is your own account, not AffilStack\'s.'),
                        TextInput::make('anthropic_model')
                            ->label('Model (optional)')
                            ->placeholder('claude-sonnet-5')
                            ->helperText('Leave blank to use the default.'),
                    ]),

                Section::make('Meta Ads MCP server')
                    ->description($this->metaMcpStatusDescription())
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_mcp_url')
                            ->label('MCP server URL')
                            ->helperText('The remote Meta Ads MCP server you set up with your own Meta App credentials.'),
                        TextInput::make('meta_mcp_token')
                            ->label('Authorization token')
                            ->password()->revealable(),
                        Placeholder::make('meta_mcp_note')
                            ->label('')
                            ->content('AffilStack never talks to Meta directly — Claude calls your MCP server\'s tools once this is connected.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function anthropicStatusDescription(): string
    {
        $settings = BrainAgentSetting::current();

        if (! $settings->anthropic_verified_at) {
            return 'Not verified yet.';
        }

        $when = $settings->anthropic_verified_at->diffForHumans();

        return $settings->anthropic_verification_status === 'success'
            ? "✓ Verified {$when} — {$settings->anthropic_verification_message}"
            : "✗ Verification failed {$when} — {$settings->anthropic_verification_message}";
    }

    protected function metaMcpStatusDescription(): string
    {
        $settings = BrainAgentSetting::current();

        if (! $settings->meta_mcp_verified_at) {
            return 'Not verified yet.';
        }

        $when = $settings->meta_mcp_verified_at->diffForHumans();

        return $settings->meta_mcp_verification_status === 'success'
            ? "✓ Verified {$when} — {$settings->meta_mcp_verification_message}"
            : "✗ Verification failed {$when} — {$settings->meta_mcp_verification_message}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        BrainAgentSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => array_filter([
                'anthropic_api_key' => $data['anthropic_api_key'] ?? null,
                'anthropic_model' => $data['anthropic_model'] ?? null,
                'meta_mcp_url' => $data['meta_mcp_url'] ?? null,
                'meta_mcp_token' => $data['meta_mcp_token'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Brain settings saved')->success()->send();
    }

    public function verifyAnthropic(): void
    {
        $this->persist($this->form->getState());
        $settings = BrainAgentSetting::current();

        $result = (new AnthropicClient(
            apiKey: (string) $settings->credential('anthropic_api_key'),
            model: (string) ($settings->credential('anthropic_model') ?: 'claude-sonnet-5'),
        ))->verifyApiKey();

        $settings->update([
            'anthropic_verified_at' => now(),
            'anthropic_verification_status' => $result['success'] ? 'success' : 'failed',
            'anthropic_verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'Anthropic key verified' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    public function verifyMetaMcp(): void
    {
        $this->persist($this->form->getState());
        $settings = BrainAgentSetting::current();

        $result = (new AnthropicClient(
            apiKey: (string) $settings->credential('anthropic_api_key'),
            model: (string) ($settings->credential('anthropic_model') ?: 'claude-sonnet-5'),
        ))->verifyMcpConnection(
            (string) $settings->credential('meta_mcp_url'),
            $settings->credential('meta_mcp_token'),
        );

        $settings->update([
            'meta_mcp_verified_at' => now(),
            'meta_mcp_verification_status' => $result['success'] ? 'success' : 'failed',
            'meta_mcp_verification_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'Meta Ads MCP connected' : 'Connection failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify_anthropic')
                ->label('Verify Claude connection')
                ->color('gray')
                ->action(fn () => $this->verifyAnthropic()),
            Action::make('verify_meta_mcp')
                ->label('Verify Meta Ads MCP connection')
                ->color('gray')
                ->action(fn () => $this->verifyMetaMcp()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
