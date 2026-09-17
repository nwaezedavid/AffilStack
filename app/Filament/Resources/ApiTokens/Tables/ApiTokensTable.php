<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'admin' ? 'danger' : 'gray'),
                TextColumn::make('last_used_at')->dateTime()->sortable()->placeholder('Never'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['user' => 'User', 'admin' => 'Admin']),
            ])
            ->recordActions([
                DeleteAction::make()->label('Revoke'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Revoke selected'),
                ]),
            ]);
    }
}
