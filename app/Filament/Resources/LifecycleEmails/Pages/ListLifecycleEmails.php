<?php

namespace App\Filament\Resources\LifecycleEmails\Pages;

use App\Filament\Resources\LifecycleEmails\LifecycleEmailResource;
use App\Models\LifecycleEmail;
use App\Support\Lifecycle\LifecycleEmailPresets;
use App\Support\Lifecycle\LifecycleSettings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class ListLifecycleEmails extends ListRecords
{
    protected static string $resource = LifecycleEmailResource::class;

    public function getSubheading(): ?string
    {
        return 'Birthday, festive-season and re-engagement emails that send themselves at '.sprintf('%02d:00', LifecycleSettings::sendHour()).' in each user\'s own timezone. Edit any of them, switch them off, or send yourself a test.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->fillForm(fn () => [
                    'send_hour' => LifecycleSettings::sendHour(),
                    'active_within_days' => LifecycleSettings::activeWithinDays(),
                    'inactive_after_days' => LifecycleSettings::inactiveAfterDays(),
                    'birthday_enabled' => LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_BIRTHDAY),
                    'festive_enabled' => LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_FESTIVE),
                    'reengagement_enabled' => LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_REENGAGEMENT),
                ])
                ->schema([
                    Section::make('Campaigns')
                        ->description('Master switches — each email also has its own on/off toggle.')
                        ->columns(3)
                        ->schema([
                            Toggle::make('birthday_enabled')->label('Birthday'),
                            Toggle::make('festive_enabled')->label('Festive season'),
                            Toggle::make('reengagement_enabled')->label('Re-engagement'),
                        ]),
                    Select::make('send_hour')
                        ->label('Send at (user\'s local time)')
                        ->options(collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00', $h)]))
                        ->required(),
                    Section::make('Activity status (admin Users list)')
                        ->columns(2)
                        ->schema([
                            TextInput::make('active_within_days')
                                ->label('"Active" = used the dashboard within (days)')
                                ->numeric()->minValue(1)->maxValue(90)->required(),
                            TextInput::make('inactive_after_days')
                                ->label('"Inactive" = no activity for more than (days)')
                                ->numeric()->minValue(2)->maxValue(365)->required()
                                ->gt('active_within_days')
                                ->helperText('In between is "Idle".'),
                        ]),
                ])
                ->action(function (array $data): void {
                    LifecycleSettings::save($data);
                    Notification::make()->title('Lifecycle settings saved')->success()->send();
                }),
            Action::make('syncPresets')
                ->label('Restore missing presets')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Adds back any preset email that was deleted. Emails you\'ve edited are never changed.')
                ->action(function (): void {
                    $created = LifecycleEmailPresets::install();
                    Notification::make()->title($created ? "Restored {$created} preset(s)" : 'All presets are already here')->success()->send();
                }),
            CreateAction::make()->label('New festive email'),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (?string $type) => LifecycleEmail::query()->when($type, fn ($q) => $q->where('type', $type))->count();

        return [
            'all' => Tab::make('All')->badge($count(null)),
            'birthday' => Tab::make('Birthday')
                ->icon('heroicon-o-cake')
                ->modifyQueryUsing(fn ($query) => $query->where('type', LifecycleEmail::TYPE_BIRTHDAY)),
            'festive' => Tab::make('Festive season')
                ->icon('heroicon-o-sparkles')
                ->badge($count(LifecycleEmail::TYPE_FESTIVE))
                ->modifyQueryUsing(fn ($query) => $query->where('type', LifecycleEmail::TYPE_FESTIVE)),
            'reengagement' => Tab::make('Re-engagement')
                ->icon('heroicon-o-arrow-uturn-left')
                ->badge($count(LifecycleEmail::TYPE_REENGAGEMENT))
                ->modifyQueryUsing(fn ($query) => $query->where('type', LifecycleEmail::TYPE_REENGAGEMENT)),
        ];
    }
}
