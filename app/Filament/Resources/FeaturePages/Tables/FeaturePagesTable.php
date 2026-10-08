<?php

namespace App\Filament\Resources\FeaturePages\Tables;

use App\Models\FeaturePage;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeaturePagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('preview')->label('')->state(fn (FeaturePage $record): ?string => $record->heroImageUrl())->height(44),
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (FeaturePage $record): string => '/features/'.$record->slug),
                TextColumn::make('menu_group')->label('Menu column')->badge()->formatStateUsing(fn (string $state): string => FeaturePage::GROUPS[$state] ?? $state),
                TextColumn::make('menu_description')->label('Menu line')->limit(50)->toggleable(),
                IconColumn::make('show_in_menu')->label('In menu')->boolean(),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                SelectFilter::make('menu_group')->label('Menu column')->options(FeaturePage::GROUPS),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (FeaturePage $record): string => $record->url())
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
