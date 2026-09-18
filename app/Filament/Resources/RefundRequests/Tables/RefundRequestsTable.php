<?php

namespace App\Filament\Resources\RefundRequests\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RefundRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('requested_at')->dateTime('M j, Y g:ia')->label('Date')->sortable(),
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->colors([
                    'success' => 'refunded',
                    'danger' => 'ineligible',
                ]),
                TextColumn::make('gateway')->placeholder('—')->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state, $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('reason')->limit(50)->wrap(),
                TextColumn::make('gateway_reference')->placeholder('—')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['refunded' => 'Refunded', 'ineligible' => 'Ineligible']),
                SelectFilter::make('gateway')->options(['flutterwave' => 'Flutterwave', 'stripe' => 'Stripe', 'paystack' => 'Paystack', 'paypal' => 'PayPal']),
            ]);
    }
}
