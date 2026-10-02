<?php

namespace App\Filament\Resources\AffiliateSwipes\Pages;

use App\Filament\Resources\AffiliateSwipes\AffiliateSwipeResource;
use App\Models\AffiliateSwipe;
use Database\Seeders\AffiliateSwipeSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateSwipes extends ListRecords
{
    protected static string $resource = AffiliateSwipeResource::class;

    public function getSubheading(): ?string
    {
        return 'Copy affiliates grab from their Marketing Kit. Each affiliate sees it with their own tracked link already inserted.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restorePresets')
                ->label('Restore missing presets')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Re-adds any built-in swipe you deleted. Your edits to existing swipes are kept.')
                ->action(function (): void {
                    $before = AffiliateSwipe::count();
                    (new AffiliateSwipeSeeder)->run();
                    Notification::make()->success()->title((AffiliateSwipe::count() - $before).' preset(s) restored')->send();
                }),
            CreateAction::make(),
        ];
    }
}
