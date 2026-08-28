<?php

namespace App\Filament\Resources\CrmContacts\Pages;

use App\Filament\Resources\CrmContacts\CrmContactResource;
use Filament\Resources\Pages\ListRecords;

class ListCrmContacts extends ListRecords
{
    protected static string $resource = CrmContactResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
