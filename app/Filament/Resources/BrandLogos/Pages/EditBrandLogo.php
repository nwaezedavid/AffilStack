<?php

namespace App\Filament\Resources\BrandLogos\Pages;

use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Models\BrandLogo;
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

    /**
     * Every uploaded logo is trimmed and resized to the same box so the
     * homepage row lines up (see LogoNormalizer).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['logo_path'] = BrandLogo::normalizedPath($data['logo_path'] ?? null);

        return $data;
    }
}
