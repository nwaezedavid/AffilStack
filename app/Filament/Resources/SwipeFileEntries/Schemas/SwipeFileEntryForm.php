<?php

namespace App\Filament\Resources\SwipeFileEntries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SwipeFileEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->columnSpanFull(),
                Select::make('type')
                    ->options(config('swipe_files.types'))
                    ->required(),
                Select::make('niche')
                    ->options(config('swipe_files.niches'))
                    ->required(),
                Textarea::make('content')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('The actual hook / subject line text, or a description of the thumbnail style.'),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Optional — why this works.'),
                TextInput::make('tags')
                    ->helperText('Comma-separated, optional — e.g. "curiosity, urgency"')
                    ->columnSpanFull()
                    ->afterStateHydrated(fn ($component, $state) => $component->state(is_array($state) ? implode(', ', $state) : $state))
                    ->dehydrateStateUsing(fn ($state) => $state ? array_map('trim', explode(',', $state)) : null),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_published')->default(true),
            ]);
    }
}
