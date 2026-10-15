<?php

namespace App\Filament\Resources\PartnerAnnouncements\Pages;

use App\Filament\Resources\PartnerAnnouncements\PartnerAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerAnnouncements extends ListRecords
{
    protected static string $resource = PartnerAnnouncementResource::class;

    public function getSubheading(): ?string
    {
        return 'News appears in every partner\'s Partner Portal with an unread badge. Use "Email partners" to also send it to their inbox.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New announcement'),
        ];
    }
}
