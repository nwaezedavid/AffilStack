<?php

namespace App\Filament\Resources\BannerAds\Pages;

use App\Filament\Resources\BannerAds\BannerAdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBannerAds extends ListRecords
{
    protected static string $resource = BannerAdResource::class;

    public function getSubheading(): ?string
    {
        return 'Ads you run here appear on every partner site that uses the auto-updating banner code, within seconds of saving. While no ad is live, partners\' slots show the built-in banner designs.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New banner ad'),
        ];
    }
}
