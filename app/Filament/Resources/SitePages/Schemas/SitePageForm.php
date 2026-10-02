<?php

namespace App\Filament\Resources\SitePages\Schemas;

use App\Filament\Support\SeoFormFields;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SitePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get, $operation) => $operation === 'create'
                        ? $set('slug', Str::slug($state))
                        : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->live(onBlur: true)
                    ->helperText('The page URL, e.g. "about" for /about. Changing this breaks any existing links to the page.'),
                RichEditor::make('content')->required()->columnSpanFull()->live(onBlur: true),
                Toggle::make('is_published')->default(true),

                Section::make('SEO & social')
                    ->description('Control how this page appears in Google, Bing and when shared on Facebook, LinkedIn, X and WhatsApp. Every field is optional — blanks fall back to the page title and the sitewide defaults in SEO > SEO settings.')
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->columnSpanFull()
                    ->components(SeoFormFields::make('site_page')),
            ]);
    }
}
