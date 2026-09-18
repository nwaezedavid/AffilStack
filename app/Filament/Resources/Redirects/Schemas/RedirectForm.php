<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Models\Redirect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                    ->helperText('Where visitors land instead — a relative path (e.g. "/new-page") or a full URL.')
                    ->rule(function (Get $get, ?Model $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            if (Redirect::wouldCreateCycle((string) $get('from_path'), (string) $value, $record?->id)) {
                                $fail('This would send visitors in a redirect loop — check where this path (or one further down the chain) already redirects to.');
                            }
                        };
                    }),
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
