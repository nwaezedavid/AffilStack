<?php

namespace App\Filament\Resources\ReferralEvents\Tables;

use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('referral.referrer.name')
                    ->label('Referrer')
                    ->searchable()
                    ->description(fn ($record) => $record->referral->referrer?->email),
                TextColumn::make('referral.referredUser.name')
                    ->label('Referred user')
                    ->searchable(),
                TextColumn::make('event_type')
                    ->badge()
                    ->colors([
                        'success' => 'first_payment',
                        'gray' => 'renewal',
                    ]),
                TextColumn::make('amount_cents')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state, $record) => '$'.number_format($state / 100, 2).' '.$record->currency)
                    ->sortable(),
                SelectColumn::make('status')
                    ->options(fn ($record) => $record->referral_payout_id === null ? [
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ] : [
                        'paid' => 'Paid',
                    ])
                    // 'paid' is only ever reached through ReferralPayoutService
                    // cascading a processed ReferralPayout (see ReferralEvent
                    // docblock) — once an event is attached to a payout this
                    // dropdown is locked so an admin can't hand-flip it back.
                    ->disabled(fn ($record) => $record->referral_payout_id !== null),
                TextColumn::make('occurred_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'paid' => 'Paid',
                ]),
                SelectFilter::make('event_type')->options([
                    'first_payment' => 'First payment',
                    'renewal' => 'Renewal',
                ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
