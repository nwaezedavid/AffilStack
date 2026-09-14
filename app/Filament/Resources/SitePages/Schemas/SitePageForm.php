<?php

namespace App\Filament\Resources\SitePages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('slug')->required()->unique(ignoreRecord: true)
                    ->helperText('The page URL, e.g. "about" for /about. Changing this breaks any existing links to the page.'),
                Textarea::make('meta_description')->rows(2)->columnSpanFull()
                    ->helperText('Shown in Google search results. Leave blank to fall back to the site default.'),
                RichEditor::make('content')->required()->columnSpanFull(),
                Toggle::make('is_published')->default(true)->columnSpanFull(),
            ]);
    }
}
