<?php

namespace App\Filament\Resources\SiteLanguages\Pages;

use App\Filament\Resources\SiteLanguages\SiteLanguageResource;
use App\Models\Translation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteLanguage extends EditRecord
{
    protected static string $resource = SiteLanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('The language is removed from the site and all of its translations are deleted.')
                ->after(fn () => Translation::query()->where('locale', $this->record->code)->delete()),
        ];
    }
}
