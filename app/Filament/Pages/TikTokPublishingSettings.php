<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\TikTokSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

/**
 * Admin control for TikTok publishing (task #2) — the developer app's
 * client key/secret, plus tracking TikTok's own Content Posting API audit
 * (TikTokSetting::approval_status). See App\Services\Social\TikTokPublishingService.
 */
class TikTokPublishingSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'TikTok Publishing';

    protected static ?string $title = 'TikTok Publishing';

    protected string $view = 'filament.pages.tiktok-publishing-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = TikTokSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'approval_status' => $settings->approval_status,
            'approval_notes' => $settings->approval_notes,
            'client_key' => $settings->credential('client_key'),
            'client_secret' => $settings->credential('client_secret'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('TikTok developer app')
                    ->description('Lets a user connect their TikTok account so AffilStack can publish a rendered UGC video for them, via the Content Posting API. Every new TikTok app defaults to a capped, private-only scope until TikTok\'s own audit approves it — track that below. Until approved, publishing falls back to "download and post manually" even for a connected account.')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Allow users to connect TikTok')
                            ->columnSpanFull(),
                        TextInput::make('client_key')->label('Client key'),
                        TextInput::make('client_secret')
                            ->label('Client secret')
                            ->password()->revealable(),
                        Select::make('approval_status')
                            ->label('Content Posting API audit status')
                            ->options([
                                'not_submitted' => 'Not submitted yet',
                                'pending' => 'Submitted — awaiting review',
                                'approved' => 'Approved',
                            ])
                            ->native(false),
                        Placeholder::make('redirect_uri')
                            ->label('Redirect URI')
                            ->helperText('Add this exact URL to the app in the TikTok Developer Portal.')
                            ->content(URL::route('social-connections.callback', ['provider' => 'tiktok'])),
                        Textarea::make('approval_notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        TikTokSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'approval_status' => $data['approval_status'] ?? 'not_submitted',
            'approval_notes' => $data['approval_notes'] ?? null,
            'credentials' => array_filter([
                'client_key' => $data['client_key'] ?? null,
                'client_secret' => $data['client_secret'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('TikTok publishing settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
