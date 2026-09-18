<?php

namespace App\Filament\Resources\BrandLogos\Pages;

use App\Filament\Resources\BrandLogos\BrandLogoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBrandLogos extends ListRecords
{
    protected static string $resource = BrandLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
