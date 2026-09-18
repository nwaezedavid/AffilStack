<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('From')->prefix('/')->searchable(),
                TextColumn::make('to_path')->label('To')->searchable(),
                TextColumn::make('status_code')->label('Type')->badge(),
                TextColumn::make('hits_count')->label('Hits')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Last hit')->dateTime()->since()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
