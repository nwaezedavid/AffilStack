<?php

namespace App\Filament\Resources\HomepageFeatures\Pages;

use App\Filament\Resources\HomepageFeatures\HomepageFeatureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomepageFeatures extends ListRecords
{
    protected static string $resource = HomepageFeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
