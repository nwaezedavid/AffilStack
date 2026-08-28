<?php

namespace App\Filament\Resources\CrmContacts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Cross-user oversight: every contact every user has saved. Edit is kept for
 * moderation/correction requests; there is deliberately no create here.
 */
class CrmContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Owner')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('company')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('source')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')->options([
                    'manual' => 'Manual',
                    'google_maps' => 'Google Maps',
                    'import' => 'Import',
                    'linkedin' => 'LinkedIn',
                ]),
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'contacted' => 'Contacted',
                    'qualified' => 'Qualified',
                    'customer' => 'Customer',
                    'unqualified' => 'Unqualified',
                ]),
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
