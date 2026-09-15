<?php

namespace App\Filament\Resources\AgentTasks\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgentTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('agent')->badge(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('risk_level')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => fn ($state) => in_array($state, ['pending', 'declined'], true),
                        'warning' => fn ($state) => in_array($state, ['scheduled', 'running'], true),
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['failed', 'rolled_back'], true),
                    ]),
                TextColumn::make('approvedBy.name')->label('Approved by')->placeholder('—'),
                TextColumn::make('scheduled_at')->dateTime('M j, Y g:ia')->sortable()->placeholder('—'),
                TextColumn::make('executed_at')->dateTime('M j, Y g:ia')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('agent')->options([
                    'security' => 'Tom (Security)',
                    'support' => 'Sam (Support)',
                    'marketing' => 'Brain (Marketing)',
                    'creative' => 'Tony (Creative)',
                ]),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'scheduled' => 'Scheduled',
                    'declined' => 'Declined',
                    'running' => 'Running',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                    'rolled_back' => 'Rolled back',
                ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
