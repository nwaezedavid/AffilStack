<?php

namespace App\Filament\Resources\LifecycleEmails\RelationManagers;

use App\Models\LifecycleEmailLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only send history for one lifecycle email: who got it, for which
 * occurrence, and whether it was sent, skipped (and why) or failed.
 */
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Send history';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (LifecycleEmailLog $record) => $record->user?->email)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => LifecycleEmailLog::STATUS_SENT,
                        'info' => LifecycleEmailLog::STATUS_QUEUED,
                        'gray' => LifecycleEmailLog::STATUS_SKIPPED,
                        'danger' => LifecycleEmailLog::STATUS_FAILED,
                    ]),
                TextColumn::make('occurrence')->label('For'),
                TextColumn::make('bonus_credits_granted')->label('Gift credits')->placeholder('—')->formatStateUsing(fn ($state) => $state ?: null),
                TextColumn::make('error')->label('Note')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('sent_at')->dateTime('M j, Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Queued')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    LifecycleEmailLog::STATUS_SENT => 'Sent',
                    LifecycleEmailLog::STATUS_QUEUED => 'Queued',
                    LifecycleEmailLog::STATUS_SKIPPED => 'Skipped',
                    LifecycleEmailLog::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
