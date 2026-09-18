<?php

namespace App\Filament\Resources\WalletTransactions\Widgets;

use App\Services\Referrals\PayoutWalletService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shows what's actually available to disburse, per currency (audit item
 * #5) — the reason this ledger exists at all is so an admin never finds out
 * the wallet is empty only when a disbursement attempt fails.
 */
class WalletBalanceWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $balances = app(PayoutWalletService::class)->balances();

        if (empty($balances)) {
            return [
                Stat::make('Wallet balance', '$0.00')
                    ->description('No funds added yet — use "Add funds" below.')
                    ->color('gray'),
            ];
        }

        return collect($balances)
            ->map(fn (int $cents, string $currency) => Stat::make("{$currency} balance", number_format($cents / 100, 2).' '.$currency)
                ->color($cents > 0 ? 'success' : 'danger'))
            ->values()
            ->all();
    }
}
