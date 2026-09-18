<?php

namespace App\Filament\Resources\Tutorials\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TutorialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->live(onBlur: true)->columnSpanFull(),
                TextInput::make('slug')
                    ->unique(ignoreRecord: true)
                    ->helperText('Used in the tutorial\'s public URL. Leave blank to generate one from the title automatically.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->rows(2)
                    ->helperText('One or two sentences shown under the title on the Learning Centre.')
                    ->columnSpanFull(),
                TextInput::make('youtube_url')
                    ->label('YouTube URL')
                    ->required()
                    ->url()
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->helperText('Paste any normal YouTube link — it embeds automatically, no embed code needed.')
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->placeholder('e.g. Getting started, Offer research, Payments')
                    ->helperText('Tutorials are grouped by category on the public page. Leave blank for "General".'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_published')->default(true)->columnSpanFull(),
            ]);
    }
}
