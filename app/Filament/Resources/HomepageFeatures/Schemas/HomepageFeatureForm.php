<?php

namespace App\Filament\Resources\HomepageFeatures\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Filament\Support\WebsiteFields;
use App\Support\Homepage\HomepageContent;
use Filament\Forms\Components\Hidden;
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
                    ->helperText('Pick an icon name from the suggestions (e.g. map-pin) for a crisp brand-coloured icon.')
                    ->datalist(array_keys(HomepageContent::iconOptions()))
                    ->maxLength(40),
                TextInput::make('sort_order')->numeric()->default(0),
                WebsiteFields::linkInput('link_url', '"Learn more" link (optional)', '/features/offer-research')
                    ->helperText('Usually the matching feature page under /features. The whole card becomes clickable.')
                    ->columnSpanFull(),
                Select::make('media_type')
                    ->label('Picture at the top of the card')
                    ->options([
                        'none' => 'Built-in screenshot (if this card has one) — no upload needed',
                        'image' => 'An image I upload',
                        'youtube' => 'A YouTube video',
                    ])
                    ->default('none')
                    ->live()
                    ->columnSpanFull(),
                WebsiteFields::imageSlot('image_path', 'Homepage → Features → top of this card', '1600 × 1000 px (16:10)', 'A screenshot of this exact feature inside AffilStack (e.g. the Google Maps lead list for "Find local customers"), with realistic data.', fn ($get): ?string => $get('default_image'))
                    ->visible(fn ($get) => $get('media_type') !== 'youtube')
                    ->columnSpanFull(),
                Hidden::make('default_image'),
                WebpFileUpload::make('image_path')
                    ->label('Upload a replacement image')
                    ->image()
                    ->disk('public')
                    ->directory('homepage-features')
                    ->helperText('1600 × 1000 px (16:10), PNG/JPG/WebP — converted to WebP automatically.')
                    ->visible(fn ($get) => $get('media_type') === 'image')
                    ->columnSpanFull(),
                WebsiteFields::youTubeInput('youtube_url', 'YouTube URL')
                    ->helperText('Paste any normal YouTube link — it embeds automatically, no embed code needed.')
                    ->visible(fn ($get) => $get('media_type') === 'youtube')
                    ->columnSpanFull(),
                WebsiteFields::youTubePreview('youtube_url')
                    ->visible(fn ($get) => $get('media_type') === 'youtube')
                    ->columnSpanFull(),
                Toggle::make('is_active')->default(true)->columnSpanFull(),
            ]);
    }
}
