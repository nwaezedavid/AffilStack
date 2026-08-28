<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('price_monthly_cents')
                    ->label('Monthly')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('price_yearly_cents')
                    ->label('Yearly')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('credits_per_month')->numeric()->sortable(),
                TextColumn::make('contact_limit')->numeric()->sortable(),
                TextColumn::make('team_seats')->numeric()->sortable(),
                IconColumn::make('is_featured')->boolean(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('subscriptions_count')
                    ->label('Active subs')
                    ->counts('subscriptions')
                    ->sortable(),
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
