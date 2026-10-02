<?php

namespace App\Filament\Resources\AffiliateBanners\Pages;

use App\Filament\Resources\AffiliateBanners\AffiliateBannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateBanners extends ListRecords
{
    protected static string $resource = AffiliateBannerResource::class;

    public function getSubheading(): ?string
    {
        $builtIn = count(config('affiliate_kit.designs')) * count(config('affiliate_kit.sizes'));

        return "Your uploads appear first in every affiliate's Marketing Kit, ahead of the {$builtIn} built-in banners, and are preferred by the rotating embed for their size. Affiliates' tracking links are added automatically.";
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
