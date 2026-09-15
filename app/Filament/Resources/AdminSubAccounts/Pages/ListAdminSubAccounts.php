<?php

namespace App\Filament\Resources\AdminSubAccounts\Pages;

use App\Filament\Resources\AdminSubAccounts\AdminSubAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminSubAccounts extends ListRecords
{
    protected static string $resource = AdminSubAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
