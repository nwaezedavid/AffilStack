<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\InstagramSetting;
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
 * Admin control for Instagram publishing (task #2) — a Meta app (app id/
 * secret), plus tracking Meta's App Review for instagram_content_publish
 * (InstagramSetting::approval_status). See
 * App\Services\Social\InstagramPublishingService.
 */
class InstagramPublishingSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Instagram Publishing';

    protected static ?string $title = 'Instagram Publishing';

    protected string $view = 'filament.pages.instagram-publishing-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = InstagramSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'approval_status' => $settings->approval_status,
            'approval_notes' => $settings->approval_notes,
            'app_id' => $settings->credential('app_id'),
            'app_secret' => $settings->credential('app_secret'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Meta app (Facebook Login for Business)')
                    ->description('Lets a user connect a Facebook Page with a linked Instagram professional account, so AffilStack can publish a rendered UGC video as a Reel. Meta requires App Review for the instagram_content_publish permission plus a verified Business Manager before this works for real users — track that below. Until approved, publishing falls back to "download and post manually" even for a connected account.')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Allow users to connect Instagram')
                            ->columnSpanFull(),
                        TextInput::make('app_id')->label('App ID'),
                        TextInput::make('app_secret')
                            ->label('App secret')
                            ->password()->revealable(),
                        Select::make('approval_status')
                            ->label('App Review status')
                            ->options([
                                'not_submitted' => 'Not submitted yet',
                                'pending' => 'Submitted — awaiting review',
                                'approved' => 'Approved',
                            ])
                            ->native(false),
                        Placeholder::make('redirect_uri')
                            ->label('Valid OAuth redirect URI')
                            ->helperText('Add this exact URL to the app in Meta for Developers, under Facebook Login for Business settings.')
                            ->content(URL::route('social-connections.callback', ['provider' => 'instagram'])),
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
        InstagramSetting::current()->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'approval_status' => $data['approval_status'] ?? 'not_submitted',
            'approval_notes' => $data['approval_notes'] ?? null,
            'credentials' => array_filter([
                'app_id' => $data['app_id'] ?? null,
                'app_secret' => $data['app_secret'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Instagram publishing settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
