<?php

namespace App\Filament\Resources\PartnerAnnouncements\Pages;

use App\Filament\Resources\PartnerAnnouncements\PartnerAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerAnnouncement extends CreateRecord
{
    protected static string $resource = PartnerAnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
