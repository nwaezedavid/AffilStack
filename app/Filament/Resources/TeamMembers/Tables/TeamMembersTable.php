<?php

namespace App\Filament\Resources\TeamMembers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TeamMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add your CEO, CFO, COO and the rest of the team — the "Meet the team" section appears on the About page as soon as one is visible.')
            ->columns([
                ImageColumn::make('photo_path')->disk('public')->circular()->label('Photo'),
                TextColumn::make('name')->searchable()->weight('medium'),
                TextColumn::make('role')->searchable()->badge()->color('gray'),
                ToggleColumn::make('is_visible')->label('Visible'),
                TextColumn::make('sort_order')->numeric()->sortable()->label('Order'),
            ])
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
