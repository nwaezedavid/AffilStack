<?php

namespace App\Filament\Pages;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Services\GitHub\GitHubSyncService;
use App\Services\Settings\ConnectionAdvisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The owner's ask: "Create a built-in feature in the admin dashboard area
 * that can automatically connect to GitHub... any future updates... made
 * automatically" — a super-admin connects this platform's own repo with a
 * Personal Access Token (no GitHub App infra exists here) and the platform
 * pushes its own code to it, on the daily `github:sync` schedule and via
 * the "Sync now" action below. See App\Services\GitHub\GitHubSyncService.
 *
 * Deliberately super-admin-only, always — like AdminSubAccountResource,
 * this does NOT use ScopedToDepartment/department "system": a PAT-
 * controlled push to a real GitHub repository is more sensitive than
 * anything else an admin sub-account's "system" department grants (viewing
 * scheduled task history, API tokens), so it is gated at least as tightly
 * as the most sensitive existing admin page — tighter, in fact, since even
 * a plain (non-super) full admin is denied.
 *
 * The personal access token field is write-only: mount() never fills it
 * from the stored credential (see credential-field rule enforced platform-
 * wide by a parallel security effort), and persist() only overwrites the
 * stored token when a new, non-blank value is actually submitted — leaving
 * it blank keeps whatever is already saved.
 */
class GitHubSyncSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'GitHub Sync';

    protected static ?string $title = 'GitHub Sync';

    protected string $view = 'filament.pages.github-sync-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public ?string $aiExplanation = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = GitHubSyncSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'repo_owner' => $settings->repo_owner,
            'repo_name' => $settings->repo_name,
            'branch' => $settings->branch,
            // personal_access_token deliberately omitted — see class docblock.
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('GitHub repository connection')
                    ->description($this->statusDescription())
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Auto-sync enabled')
                            ->helperText('When on, the daily schedule pushes any pending code changes automatically. "Sync now" below always works regardless of this toggle.')
                            ->columnSpanFull(),
                        TextInput::make('repo_owner')
                            ->label('Repository owner')
                            ->helperText('The GitHub username or organization that owns the repo.')
                            ->maxLength(255),
                        TextInput::make('repo_name')
                            ->label('Repository name')
                            ->maxLength(255),
                        TextInput::make('branch')
                            ->label('Target branch')
                            ->placeholder('main')
                            ->maxLength(255),
                        TextInput::make('personal_access_token')
                            ->label('Personal access token')
                            ->password()->revealable()
                            ->placeholder($this->tokenPlaceholder())
                            ->helperText('Needs the "repo" scope (classic token) or "Contents: Read and write" (fine-grained token). Never re-displayed once saved — leave blank to keep the current token, or paste a new one to replace it.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function tokenPlaceholder(): string
    {
        return filled(GitHubSyncSetting::current()->credential('personal_access_token'))
            ? 'Already saved — leave blank to keep the current token'
            : 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    }

    protected function statusDescription(): string
    {
        $settings = GitHubSyncSetting::current();

        if (! $settings->last_sync_at) {
            return 'Not connected yet. Enter a personal access token and repository below, then Verify connection or Sync now.';
        }

        $when = $settings->last_sync_at->diffForHumans();

        $html = match ($settings->last_sync_status) {
            'success' => "✓ {$settings->last_sync_message} ({$when})",
            'blocked' => "⚠ Refused to sync {$when} — {$settings->last_sync_message}",
            default => "✗ Failed {$when} — {$settings->last_sync_message}",
        };

        if ($this->aiExplanation) {
            $html .= "\n✦ AI assistant: {$this->aiExplanation}";
        }

        return $html;
    }

    /**
     * @return Collection<int, GitHubSyncRun>
     */
    public function recentRuns(): Collection
    {
        return GitHubSyncRun::query()->latest()->limit(10)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = GitHubSyncSetting::current();
        $credentials = $settings->credentials ?? [];

        if (filled($data['personal_access_token'] ?? null)) {
            $credentials['personal_access_token'] = $data['personal_access_token'];
        }

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'repo_owner' => trim((string) ($data['repo_owner'] ?? '')) ?: null,
            'repo_name' => trim((string) ($data['repo_name'] ?? '')) ?: null,
            'branch' => trim((string) ($data['branch'] ?? '')) ?: 'main',
            'credentials' => $credentials,
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('GitHub Sync settings saved')->success()->send();
    }

    public function verifyConnection(GitHubSyncService $service): void
    {
        $this->persist($this->form->getState());
        $settings = GitHubSyncSetting::current();

        $result = $service->verifyConnection($settings);

        $settings->update([
            'last_sync_status' => $result['success'] ? 'success' : 'failed',
            'last_sync_at' => now(),
            'last_sync_message' => $result['message'],
        ]);

        Notification::make()
            ->title($result['success'] ? 'GitHub connection verified' : 'Verification failed')
            ->body($result['message'])
            ->status($result['success'] ? 'success' : 'danger')
            ->send();
    }

    public function syncNow(GitHubSyncService $service): void
    {
        $this->persist($this->form->getState());
        $settings = GitHubSyncSetting::current();

        $run = $service->sync($settings, GitHubSyncRun::TRIGGER_MANUAL);

        Notification::make()
            ->title(match ($run->status) {
                GitHubSyncRun::STATUS_SUCCESS => 'Sync complete',
                GitHubSyncRun::STATUS_BLOCKED => 'Sync refused',
                default => 'Sync failed',
            })
            ->body($run->message)
            ->status(match ($run->status) {
                GitHubSyncRun::STATUS_SUCCESS => 'success',
                GitHubSyncRun::STATUS_BLOCKED => 'warning',
                default => 'danger',
            })
            ->persistent()
            ->send();
    }

    /**
     * Turns the last failed/blocked sync into a plain-English explanation
     * via the platform-wide Connections Health advisor (audit item #6) —
     * only the connection's name, description, and its own already-
     * sanitized status message are sent, never a credential.
     */
    public function explain(ConnectionAdvisor $advisor): void
    {
        $settings = GitHubSyncSetting::current();

        if (! in_array($settings->last_sync_status, ['failed', 'blocked'], true)) {
            return;
        }

        $this->aiExplanation = $advisor->explain(
            'GitHub Sync',
            "Pushes this platform's own code changes to a connected GitHub repository, on a daily schedule and on demand.",
            (string) $settings->last_sync_message,
        );

        Notification::make()
            ->title('GitHub Sync — AI explanation')
            ->body($this->aiExplanation)
            ->info()
            ->persistent()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyConnection')
                ->label('Verify connection')
                ->color('gray')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->action(fn (GitHubSyncService $service) => $this->verifyConnection($service)),
            Action::make('syncNow')
                ->label('Sync now')
                ->color('primary')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('This commits and pushes any pending changes in this application\'s own working copy to the connected GitHub repository right now.')
                ->action(fn (GitHubSyncService $service) => $this->syncNow($service)),
            Action::make('explain')
                ->label('Explain with AI')
                ->color('gray')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => in_array(GitHubSyncSetting::current()->last_sync_status, ['failed', 'blocked'], true))
                ->action(fn (ConnectionAdvisor $advisor) => $this->explain($advisor)),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
