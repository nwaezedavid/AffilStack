<?php

namespace App\Filament\Resources\SwipeFileEntries\Pages;

use App\Filament\Resources\SwipeFileEntries\SwipeFileEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSwipeFileEntries extends ListRecords
{
    protected static string $resource = SwipeFileEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
