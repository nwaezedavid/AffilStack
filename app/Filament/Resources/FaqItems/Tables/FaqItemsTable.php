<?php

namespace App\Filament\Resources\FaqItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')->searchable()->limit(60),
                TextColumn::make('category')->badge(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->options([
                    'general' => 'General',
                    'billing' => 'Billing',
                    'features' => 'Features',
                    'account' => 'Account',
                ]),
                TernaryFilter::make('is_published'),
            ])
            ->reorderable('sort_order')
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
