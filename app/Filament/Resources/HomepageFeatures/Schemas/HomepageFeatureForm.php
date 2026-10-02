<?php

namespace App\Filament\Resources\HomepageFeatures\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Support\Homepage\HomepageContent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HomepageFeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->columnSpanFull(),
                Textarea::make('description')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('One or two sentences — this is the card copy visitors actually read.'),
                TextInput::make('icon')
                    ->helperText('Pick an icon name from the suggestions (e.g. map-pin) for a crisp brand-coloured icon, or type a single emoji.')
                    ->datalist(array_keys(HomepageContent::iconOptions()))
                    ->maxLength(40),
                TextInput::make('sort_order')->numeric()->default(0),
                Select::make('media_type')
                    ->label('Media')
                    ->options([
                        'none' => 'None',
                        'image' => 'Image',
                        'youtube' => 'YouTube video',
                    ])
                    ->default('none')
                    ->live()
                    ->columnSpanFull(),
                WebpFileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('homepage-features')
                    ->helperText('A 1280×720 px (16:9) screenshot of this exact feature inside AffilStack (e.g. the Google Maps lead list for "Buyer finder"), cropped tight with no browser bars. PNG or WebP under 600 KB. Shown across the top of the card.')
                    ->visible(fn ($get) => $get('media_type') === 'image')
                    ->columnSpanFull(),
                TextInput::make('youtube_url')
                    ->label('YouTube URL')
                    ->url()
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->helperText('Paste any normal YouTube link — it embeds automatically, no embed code needed.')
                    ->visible(fn ($get) => $get('media_type') === 'youtube')
                    ->columnSpanFull(),
                Toggle::make('is_active')->default(true)->columnSpanFull(),
            ]);
    }
}
