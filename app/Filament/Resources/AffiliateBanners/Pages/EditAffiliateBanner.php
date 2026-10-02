<?php

namespace App\Filament\Resources\AffiliateBanners\Pages;

use App\Filament\Resources\AffiliateBanners\AffiliateBannerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateBanner extends EditRecord
{
    protected static string $resource = AffiliateBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
