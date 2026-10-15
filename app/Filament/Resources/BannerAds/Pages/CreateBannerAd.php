<?php

namespace App\Filament\Resources\BannerAds\Pages;

use App\Filament\Resources\BannerAds\BannerAdResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBannerAd extends CreateRecord
{
    protected static string $resource = BannerAdResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
