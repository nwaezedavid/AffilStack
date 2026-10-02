<?php

namespace App\Filament\Resources\PageSeos\Pages;

use App\Filament\Resources\PageSeos\PageSeoResource;
use App\Models\PageSeo;
use Filament\Resources\Pages\ListRecords;

class ListPageSeos extends ListRecords
{
    protected static string $resource = PageSeoResource::class;

    protected ?string $subheading = 'Every public page and how well it is set up for Google and social sharing. Open a page to set its SEO title, description, keywords, social image and schema — a live Google and social preview shows the result as you type.';

    public function mount(): void
    {
        PageSeo::syncRegistry();

        parent::mount();
    }
}
