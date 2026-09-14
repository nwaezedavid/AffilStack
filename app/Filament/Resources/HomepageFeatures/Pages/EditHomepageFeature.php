<?php

namespace App\Filament\Resources\HomepageFeatures\Pages;

use App\Filament\Resources\HomepageFeatures\HomepageFeatureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomepageFeature extends EditRecord
{
    protected static string $resource = HomepageFeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
