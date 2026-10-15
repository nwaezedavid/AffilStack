<?php

namespace App\Filament\Resources\PartnerAnnouncements\Pages;

use App\Filament\Resources\PartnerAnnouncements\PartnerAnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerAnnouncement extends EditRecord
{
    protected static string $resource = PartnerAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
