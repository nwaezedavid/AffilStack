<?php

namespace App\Filament\Resources\BrandLogos\Pages;

use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Models\BrandLogo;
use Filament\Resources\Pages\CreateRecord;

class CreateBrandLogo extends CreateRecord
{
    protected static string $resource = BrandLogoResource::class;

    /**
     * Every uploaded logo is trimmed and resized to the same box so the
     * homepage row lines up (see LogoNormalizer).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['logo_path'] = BrandLogo::normalizedPath($data['logo_path'] ?? null);

        return $data;
    }
}
