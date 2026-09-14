<?php

namespace App\Filament\Resources\ScheduledTaskRuns\Tables;

use App\Models\ScheduledTaskRun;
use Carbon\CarbonInterface;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledTaskRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('task')->searchable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'success' => 'success',
                    'failed' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('finished_at')->dateTime()->sortable()->placeholder('still running / never finished'),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn ($record) => $record->finished_at
                        ? $record->started_at->diffForHumans($record->finished_at, syntax: CarbonInterface::DIFF_ABSOLUTE)
                        : '—'),
                TextColumn::make('output')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('task')->options(
                    fn () => ScheduledTaskRun::query()->distinct()->pluck('task', 'task')->all()
                ),
                SelectFilter::make('status')->options([
                    'running' => 'Running',
                    'success' => 'Success',
                    'failed' => 'Failed',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
