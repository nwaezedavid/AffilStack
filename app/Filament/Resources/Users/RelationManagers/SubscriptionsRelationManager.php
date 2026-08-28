<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Admin oversight only — subscription history is created by the billing flow
 * (Flutterwave checkout + webhook), never edited by hand here.
 */
class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('status')->badge(),
                TextColumn::make('billing_cycle'),
                TextColumn::make('current_period_end')->label('Renews')->dateTime('M j, Y'),
                TextColumn::make('created_at')->dateTime('M j, Y')->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
