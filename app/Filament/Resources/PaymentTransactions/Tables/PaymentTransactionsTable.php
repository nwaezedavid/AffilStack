<?php

namespace App\Filament\Resources\PaymentTransactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('tx_ref')->searchable()->copyable()->limit(24),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => '$'.number_format($state / 100, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'successful',
                        'warning' => 'pending',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),
                TextColumn::make('processed_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'successful' => 'Successful',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ]),
                SelectFilter::make('type')->options([
                    'subscription' => 'Subscription',
                    'credit_topup' => 'Credit top-up',
                    'addon' => 'Add-on',
                ]),
                SelectFilter::make('gateway')->options([
                    'stripe' => 'Stripe',
                    'flutterwave' => 'Flutterwave',
                ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
