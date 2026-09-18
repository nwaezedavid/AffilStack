<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Filament\Resources\WalletTransactions\Widgets\WalletBalanceWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            WalletBalanceWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add funds'),
        ];
    }
}
