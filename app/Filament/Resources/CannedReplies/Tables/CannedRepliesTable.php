<?php

namespace App\Filament\Resources\CannedReplies\Tables;

use App\Models\CannedReply;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CannedRepliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('body')->limit(80)->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => CannedReply::STATUS_ACTIVE,
                        'warning' => CannedReply::STATUS_SUGGESTED,
                        'gray' => CannedReply::STATUS_ARCHIVED,
                    ]),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === CannedReply::SOURCE_AI_SUGGESTED ? 'Sam' : 'Manual')
                    ->colors(['info' => CannedReply::SOURCE_AI_SUGGESTED, 'gray' => CannedReply::SOURCE_MANUAL]),
                TextColumn::make('usage_count')->label('Used')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    CannedReply::STATUS_ACTIVE => 'Active',
                    CannedReply::STATUS_SUGGESTED => 'Suggested',
                    CannedReply::STATUS_ARCHIVED => 'Archived',
                ]),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CannedReply $record) => $record->status === CannedReply::STATUS_SUGGESTED)
                    ->action(function (CannedReply $record): void {
                        $record->update(['status' => CannedReply::STATUS_ACTIVE]);
                        Notification::make()->title('Template activated')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
