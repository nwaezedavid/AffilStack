<?php

namespace App\Filament\Resources\SwipeFileEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SwipeFileEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => config("swipe_files.types.{$state}", $state)),
                TextColumn::make('niche')->formatStateUsing(fn (string $state) => config("swipe_files.niches.{$state}", $state)),
                TextColumn::make('content')->limit(60)->searchable(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(config('swipe_files.types')),
                SelectFilter::make('niche')->options(config('swipe_files.niches')),
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
