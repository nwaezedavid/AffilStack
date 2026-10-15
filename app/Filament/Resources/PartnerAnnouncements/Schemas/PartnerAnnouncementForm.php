<?php

namespace App\Filament\Resources\PartnerAnnouncements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PartnerAnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Black Friday: 30% off annual plans for your audience')
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->required()
                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'undo', 'redo'])
                    ->columnSpanFull(),
                DateTimePicker::make('published_at')
                    ->label('Publish at')
                    ->seconds(false)
                    ->default(now())
                    ->helperText('Leave empty to keep it as a draft. A future time publishes it then.'),
                Toggle::make('is_pinned')
                    ->label('Pin to the top')
                    ->helperText('Pinned news stays first until you unpin it.'),
            ]);
    }
}
