<?php

namespace App\Filament\Resources\BrandLogos\Schemas;

use App\Filament\Support\WebpFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BrandLogoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->helperText('The brand\'s name — used as the logo\'s alt text.')
                    ->columnSpanFull(),
                WebpFileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('brand-logos')
                    ->required()
                    ->helperText('Any PNG, JPG or WebP. It is trimmed and resized automatically to a uniform 320 × 120 px box (transparent background) so every logo in the homepage row is the same size. A transparent PNG works best.')
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->label('Link (optional)')
                    ->url()
                    ->placeholder('https://')
                    ->helperText('Where the logo links to, if anywhere.')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
