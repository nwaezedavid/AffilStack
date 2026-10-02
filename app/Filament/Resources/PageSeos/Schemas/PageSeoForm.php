<?php

namespace App\Filament\Resources\PageSeos\Schemas;

use App\Filament\Support\SeoFormFields;
use Filament\Schemas\Schema;

class PageSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(SeoFormFields::make('page_seo'));
    }
}
