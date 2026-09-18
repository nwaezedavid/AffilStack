<?php

namespace App\Filament\Resources\BrandLogos\Pages;

use App\Filament\Resources\BrandLogos\BrandLogoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBrandLogo extends EditRecord
{
    protected static string $resource = BrandLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
