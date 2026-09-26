<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Only ever used to record a top-up (audit item #5) — the only kind of
 * wallet_transactions row an admin creates directly; a "payout" row is only
 * ever written by PayoutDisbursementService, never through this form. See
 * WalletTransactionResource::canEdit()/canDelete() for why nothing here is
 * ever changed afterward.
 */
class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currency')
                    ->label('Currency')
                    ->options(['USD' => 'USD', 'NGN' => 'NGN'])
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount added')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required()
                    // Must stay dehydrated: CreateWalletTransaction reads
                    // $data['amount'] (it isn't a model column, it's
                    // converted to amount_cents there).
                    ->helperText('The amount you actually deposited into your Flutterwave/PayPal balance elsewhere — this just records it here so the wallet can draw against it.'),
                TextInput::make('reference')
                    ->label('Reference (optional)')
                    ->maxLength(255)
                    ->helperText('A bank deposit reference or transfer ID, for your own records.'),
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->rows(2),
            ]);
    }
}
