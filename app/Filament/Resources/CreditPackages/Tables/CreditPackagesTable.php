<?php

namespace App\Filament\Resources\CreditPackages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreditPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('credits')->numeric()->sortable(),
                TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label('Per credit')
                    ->state(fn ($record) => $record->credits > 0 ? '$'.number_format(($record->price_cents / 100) / $record->credits, 4) : '—'),
                IconColumn::make('is_featured')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([])
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
