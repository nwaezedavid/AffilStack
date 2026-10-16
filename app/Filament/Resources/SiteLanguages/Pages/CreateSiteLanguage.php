<?php

namespace App\Filament\Resources\SiteLanguages\Pages;

use App\Filament\Resources\SiteLanguages\SiteLanguageResource;
use App\Jobs\ScanSiteForTranslations;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteLanguage extends CreateRecord
{
    protected static string $resource = SiteLanguageResource::class;

    protected function afterCreate(): void
    {
        // Find and translate the website's text straight away.
        ScanSiteForTranslations::dispatch([$this->record->code]);
    }
}
