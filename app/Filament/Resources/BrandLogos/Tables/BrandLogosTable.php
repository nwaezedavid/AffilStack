<?php

namespace App\Filament\Resources\BrandLogos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandLogosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('logo_path')->disk('public')->label('Logo')->height(32),
                TextColumn::make('name')->searchable(),
                TextColumn::make('url')->label('Link')->limit(40)->placeholder('—'),
                TextColumn::make('sort_order')->numeric()->sortable(),
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
