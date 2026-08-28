<?php

namespace App\Filament\Resources\CrmContacts\Pages;

use App\Filament\Resources\CrmContacts\CrmContactResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmContact extends EditRecord
{
    protected static string $resource = CrmContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
