<?php

namespace App\Filament\Resources\CannedReplies\Pages;

use App\Filament\Resources\CannedReplies\CannedReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCannedReplies extends ListRecords
{
    protected static string $resource = CannedReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
