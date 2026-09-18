<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\WalletTransactions\Pages\CreateWalletTransaction;
use App\Filament\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionForm;
use App\Filament\Resources\WalletTransactions\Tables\WalletTransactionsTable;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The affiliate payout wallet (audit item #5) — an admin tops it up here
 * with money they've actually moved into their Flutterwave/PayPal balance,
 * and PayoutDisbursementService draws it down automatically when disbursing
 * a ReferralPayout (see the "Disburse from wallet" action on Referral
 * Payouts). Every row is an immutable ledger entry — see
 * create_wallet_transactions_table's migration comment — so nothing here is
 * ever edited or deleted, only appended to via "Add funds" or an automatic
 * disbursement.
 */
class WalletTransactionResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payout Wallet';

    protected static ?string $modelLabel = 'wallet transaction';

    public static function form(Schema $schema): Schema
    {
        return WalletTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletTransactionsTable::configure($table);
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletTransactions::route('/'),
            'create' => CreateWalletTransaction::route('/create'),
        ];
    }
}
