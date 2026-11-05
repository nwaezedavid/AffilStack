<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only customer wallet history. Rows are append-only — corrections are
 * made with the "Adjust wallet" action on the edit page.
 */
class CustomerWalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerWalletTransactions';

    protected static ?string $title = 'Wallet history';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->canAccessDepartment('billing') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('M j, Y H:i'),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => CustomerWalletTransaction::TYPE_LABELS[$state] ?? $state),
                TextColumn::make('description')->wrap(),
                TextColumn::make('note')->wrap()->placeholder('—'),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, CustomerWalletTransaction $record) => ($state < 0 ? '−' : '+').CustomerWallet::format(abs($state), $record->currency))
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('balance_after_minor')
                    ->label('Balance')
                    ->formatStateUsing(fn (int $state, CustomerWalletTransaction $record) => CustomerWallet::format($state, $record->currency)),
                TextColumn::make('createdBy.name')->label('By')->placeholder('System'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
