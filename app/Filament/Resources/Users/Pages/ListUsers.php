<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\UserActivityOverview;
use App\Support\Lifecycle\LifecycleSettings;
use App\Support\Lifecycle\UserActivity;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserActivityOverview::class,
        ];
    }

    /**
     * Active / Idle / Inactive / Never logged in — thresholds are set in
     * Support > Lifecycle Emails > Settings (see LifecycleSettings).
     */
    public function getTabs(): array
    {
        $count = fn (string $status) => UserActivity::scope(UserResource::getEloquentQuery(), $status)->count();
        $active = LifecycleSettings::activeWithinDays();
        $inactive = LifecycleSettings::inactiveAfterDays();

        return [
            'all' => Tab::make('All users'),
            UserActivity::ACTIVE => Tab::make('Active')
                ->icon('heroicon-m-signal')
                ->badge($count(UserActivity::ACTIVE))
                ->badgeColor('success')
                ->badgeTooltip("Used the dashboard in the last {$active} days")
                ->modifyQueryUsing(fn ($query) => UserActivity::scope($query, UserActivity::ACTIVE)),
            UserActivity::IDLE => Tab::make('Idle')
                ->icon('heroicon-m-pause-circle')
                ->badge($count(UserActivity::IDLE))
                ->badgeColor('warning')
                ->badgeTooltip("Last active {$active}–{$inactive} days ago")
                ->modifyQueryUsing(fn ($query) => UserActivity::scope($query, UserActivity::IDLE)),
            UserActivity::INACTIVE => Tab::make('Inactive')
                ->icon('heroicon-m-moon')
                ->badge($count(UserActivity::INACTIVE))
                ->badgeColor('danger')
                ->badgeTooltip("No activity for more than {$inactive} days")
                ->modifyQueryUsing(fn ($query) => UserActivity::scope($query, UserActivity::INACTIVE)),
            UserActivity::NEVER => Tab::make('Never logged in')
                ->icon('heroicon-m-minus-circle')
                ->badge($count(UserActivity::NEVER))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => UserActivity::scope($query, UserActivity::NEVER)),
        ];
    }
}
