<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\AgentActivity;
use App\Models\SiteSetting;
use App\Services\Agents\Revenue\RevenueAgentSettings as Settings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * AI Agents > Revenue agents: the agents that work for AffilStack itself —
 * Churn saver (win-back emails) and Upgrade nudge (plan-limit prompts).
 */
class RevenueAgentSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'ai_agents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Revenue agents';

    protected static ?string $title = 'Revenue agents: Churn saver & Upgrade nudge';

    protected string $view = 'filament.pages.revenue-agent-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'churn_enabled' => Settings::churnEnabled(),
            'churn_inactive_days' => Settings::churnInactiveDays(),
            'churn_offer' => Settings::churnOffer(),
            'nudge_enabled' => Settings::nudgeEnabled(),
            'nudge_threshold' => Settings::nudgeThreshold(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $count = fn (string $agent): int => AgentActivity::query()->where('agent', $agent)->where('created_at', '>=', now()->subDays(30))->count();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Churn saver')
                    ->description('Writes personally to paying customers who go quiet, and to those whose renewal payment failed, showing what AffilStack has done for them. Sent in the last 30 days: '.$count('churn_saver').'.')
                    ->components([
                        Toggle::make('churn_enabled')->label('Send win-back emails'),
                        TextInput::make('churn_inactive_days')->label('Write after this many quiet days')->numeric()->minValue(3)->maxValue(90)->required(),
                        TextInput::make('churn_offer')
                            ->label('Win-back offer (optional)')
                            ->maxLength(200)
                            ->placeholder('e.g. Reply "STAY" and we\'ll add 200 free credits to your account.')
                            ->helperText('Shown in bold in the email. Leave blank to send without an offer.'),
                    ]),
                Section::make('Upgrade nudge')
                    ->description('When a customer reaches the threshold of a plan limit (credits, leads, contacts or products), the dashboard shows their results beside an upgrade button, and they get one email per limit per month. Sent in the last 30 days: '.$count('upgrade_nudge').'.')
                    ->components([
                        Toggle::make('nudge_enabled')->label('Show upgrade nudges'),
                        TextInput::make('nudge_threshold')->label('Nudge at this % of a limit')->numeric()->minValue(50)->maxValue(100)->suffix('%')->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('agents_churn_saver_enabled', ! empty($data['churn_enabled']) ? '1' : '0');
        SiteSetting::set('agents_churn_saver_inactive_days', (string) (int) $data['churn_inactive_days']);
        SiteSetting::set('agents_churn_saver_offer', (string) ($data['churn_offer'] ?? ''));
        SiteSetting::set('agents_upgrade_nudge_enabled', ! empty($data['nudge_enabled']) ? '1' : '0');
        SiteSetting::set('agents_upgrade_nudge_threshold', (string) (int) $data['nudge_threshold']);

        Notification::make()->title('Revenue agents saved')->success()->send();
    }
}
