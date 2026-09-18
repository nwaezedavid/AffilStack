<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('from_path')
                    ->label('From')
                    ->required()
                    ->maxLength(255)
                    ->prefix('/')
                    ->helperText('The old/dead path, without a leading slash — e.g. "old-page" for yoursite.com/old-page.')
                    ->unique(ignoreRecord: true)
                    ->formatStateUsing(fn (?string $state) => $state ? ltrim($state, '/') : $state)
                    ->dehydrateStateUsing(fn (?string $state) => ltrim((string) $state, '/')),
                TextInput::make('to_path')
                    ->label('To')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Where visitors land instead — a relative path (e.g. "/new-page") or a full URL.'),
                Select::make('status_code')
                    ->label('Redirect type')
                    ->options([
                        301 => '301 — Permanent',
                        302 => '302 — Temporary',
                    ])
                    ->default(301)
                    ->required(),
            ]);
    }
}
