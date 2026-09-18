<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use App\Models\WalletTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('M j, Y g:ia')->label('Date')->sortable(),
                TextColumn::make('type')->badge()->colors([
                    'success' => 'top_up',
                    'danger' => 'payout',
                    'gray' => 'adjustment',
                ]),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, WalletTransaction $record) => ($state >= 0 ? '+' : '−').number_format(abs($state) / 100, 2).' '.$record->currency)
                    ->color(fn (int $state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('gateway')->placeholder('—')->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
                TextColumn::make('referralPayout.user.name')->label('Affiliate')->placeholder('—'),
                TextColumn::make('reference')->placeholder('—')->limit(30),
                TextColumn::make('note')->placeholder('—')->limit(40)->wrap(),
                TextColumn::make('createdBy.name')->label('Added by')->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('currency')->options(['USD' => 'USD', 'NGN' => 'NGN']),
                SelectFilter::make('type')->options(['top_up' => 'Top-up', 'payout' => 'Payout', 'adjustment' => 'Adjustment']),
            ]);
    }
}
