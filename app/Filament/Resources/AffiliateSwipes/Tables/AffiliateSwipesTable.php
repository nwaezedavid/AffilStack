<?php

namespace App\Filament\Resources\AffiliateSwipes\Tables;

use App\Models\AffiliateSwipe;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateSwipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->searchable()->description(fn (AffiliateSwipe $record): ?string => $record->subject),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => AffiliateSwipe::TYPES[$state] ?? $state),
                TextColumn::make('platform')->formatStateUsing(fn (?string $state): string => config("affiliate_kit.social_platforms.{$state}", '—'))->placeholder('—'),
                ToggleColumn::make('is_active')->label('Active'),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(AffiliateSwipe::TYPES),
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
