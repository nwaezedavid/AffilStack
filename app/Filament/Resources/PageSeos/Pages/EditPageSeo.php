<?php

namespace App\Filament\Resources\PageSeos\Pages;

use App\Filament\Resources\PageSeos\PageSeoResource;
use App\Models\PageSeo;
use App\Support\Seo\PublicPages;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPageSeo extends EditRecord
{
    protected static string $resource = PageSeoResource::class;

    public function getTitle(): string
    {
        /** @var PageSeo $record */
        $record = $this->getRecord();

        return 'SEO: '.$record->label();
    }

    public function getSubheading(): ?string
    {
        /** @var PageSeo $record */
        $record = $this->getRecord();

        return PublicPages::description($record->route_name);
    }

    protected function getHeaderActions(): array
    {
        /** @var PageSeo $record */
        $record = $this->getRecord();

        return [
            Action::make('view_live')
                ->label('View page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url($record->url(), shouldOpenInNewTab: true)
                ->visible($record->url() !== null),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
