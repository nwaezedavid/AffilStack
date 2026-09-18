<?php

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('avatar_path')->disk('public')->circular()->label('Photo'),
                TextColumn::make('author_name')->searchable(),
                TextColumn::make('author_role')->placeholder('—'),
                TextColumn::make('quote')->limit(60)->wrap(),
                TextColumn::make('rating')->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).str_repeat('☆', 5 - $state) : '—'),
                IconColumn::make('is_published')->boolean(),
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
