<?php

namespace App\Filament\Resources\AffiliateBanners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AffiliateBannerForm
{
    /**
     * A plain FileUpload, deliberately NOT WebpFileUpload: affiliates paste
     * these into emails and forums, and Outlook and many forums can't show
     * WebP. The file is kept exactly as uploaded (an animated GIF stays
     * animated). SVG is refused — it can carry script.
     */
    public static function configure(Schema $schema): Schema
    {
        $sizes = collect(config('affiliate_kit.sizes'))
            ->mapWithKeys(fn (array $size, string $key): array => [$key => str_replace('x', '×', $key).' — '.$size['label']])
            ->all();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Shown to affiliates in the banner gallery, e.g. "Black Friday — gold".')
                    ->columnSpanFull(),
                Select::make('size')
                    ->options($sizes)
                    ->required()
                    ->default('300x250')
                    ->helperText('Upload the image at exactly this size (or 2× for extra sharpness).'),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->maxLength(150)
                    ->placeholder('Try AffilStack')
                    ->helperText('Read by screen readers and shown when images are blocked in email.'),
                FileUpload::make('image_path')
                    ->label('Banner image')
                    ->image()
                    ->disk('public')
                    ->directory('affiliate-banners')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif'])
                    ->rules(['mimes:png,jpg,jpeg,gif'])
                    ->maxSize(2048)
                    ->required()
                    ->helperText('PNG, JPG or GIF (max 2 MB). Kept exactly as uploaded so it works in email.')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }
}
