<?php

namespace App\Filament\Resources\CrmContacts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * CRM/email dashboard: admin oversight of what's actually been sent to a
 * contact (see CrmEmailService) — never created by hand here.
 */
class EmailSendsRelationManager extends RelationManager
{
    protected static string $relationship = 'emailSends';

    protected static ?string $title = 'Email history';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')->limit(40),
                TextColumn::make('sequence_step')->label('Step'),
                TextColumn::make('status')
                    ->badge()
                    ->colors(['success' => 'sent', 'danger' => 'failed', 'gray' => 'queued']),
                IconColumn::make('opened_at')->label('Opened')->boolean(),
                IconColumn::make('first_clicked_at')->label('Clicked')->boolean(),
                TextColumn::make('sent_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
