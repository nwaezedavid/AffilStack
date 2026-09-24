<?php

namespace App\Filament\Resources\NotFoundLogs\Tables;

use App\Filament\Resources\NotFoundLogs\Actions\CreateRedirectAction;
use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotFoundLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('hits_count', 'desc')
            ->recordUrl(fn ($record) => NotFoundLogResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('path')->label('Path')->prefix('/')->searchable(),
                TextColumn::make('hits_count')->label('Hits')->numeric()->sortable(),
                TextColumn::make('referer')->label('Referer')->limit(40)->placeholder('—'),
                TextColumn::make('first_seen_at')->label('First seen')->dateTime()->since(),
                TextColumn::make('last_seen_at')->label('Last seen')->dateTime()->since()->sortable(),
            ])
            ->recordActions([
                CreateRedirectAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
