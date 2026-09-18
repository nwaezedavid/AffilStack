<?php

namespace App\Filament\Resources\CreditPackages\Pages;

use App\Filament\Resources\CreditPackages\CreditPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditPackages extends ListRecords
{
    protected static string $resource = CreditPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
