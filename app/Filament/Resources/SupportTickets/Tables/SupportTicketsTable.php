<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_reply_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email),
                TextColumn::make('subject')->searchable()->limit(40),
                TextColumn::make('category')->badge(),
                TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'danger' => 'high',
                        'warning' => 'normal',
                        'gray' => 'low',
                    ]),
                SelectColumn::make('status')
                    ->options([
                        'open' => 'Open',
                        'pending' => 'Pending',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                SelectColumn::make('assigned_to')
                    ->label('Assigned to')
                    ->options(fn () => User::role(['admin', 'support'])->pluck('name', 'id'))
                    ->placeholder('Unassigned'),
                TextColumn::make('csat_rating')
                    ->label('CSAT')
                    ->formatStateUsing(fn ($state) => $state ? str_repeat('⭐', (int) $state) : '—'),
                TextColumn::make('last_reply_at')
                    ->label('Last activity')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'pending' => 'Pending',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ]),
                SelectFilter::make('priority')->options([
                    'low' => 'Low',
                    'normal' => 'Normal',
                    'high' => 'High',
                ]),
                SelectFilter::make('category')->options([
                    'billing' => 'Billing',
                    'technical' => 'Technical',
                    'feature_request' => 'Feature request',
                    'other' => 'Other',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
