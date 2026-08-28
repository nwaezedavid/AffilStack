<?php

namespace App\Filament\Resources\SwipeFileEntries\Pages;

use App\Filament\Resources\SwipeFileEntries\SwipeFileEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSwipeFileEntry extends EditRecord
{
    protected static string $resource = SwipeFileEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
