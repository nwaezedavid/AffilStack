<?php

namespace App\Filament\Resources\AffiliateBanners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateBannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')->disk('public')->label('Banner')->height(48),
                TextColumn::make('title')->searchable(),
                TextColumn::make('size')->badge()->formatStateUsing(fn (string $state): string => str_replace('x', '×', $state)),
                ToggleColumn::make('is_active')->label('Active'),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('size')->options(collect(config('affiliate_kit.sizes'))->mapWithKeys(fn (array $size, string $key): array => [$key => str_replace('x', '×', $key)])->all()),
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
