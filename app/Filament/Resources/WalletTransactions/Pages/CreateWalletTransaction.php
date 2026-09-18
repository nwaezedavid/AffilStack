<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Services\Referrals\PayoutWalletService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Routes through PayoutWalletService::topUp() rather than a plain Eloquent
 * create, so this form can only ever add a "top_up" row — the only kind of
 * wallet_transactions row an admin is allowed to write directly (audit item
 * #5). A "payout" row only ever comes from PayoutDisbursementService.
 */
class CreateWalletTransaction extends CreateRecord
{
    protected static string $resource = WalletTransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PayoutWalletService::class)->topUp(
            $data['currency'],
            (int) round(((float) $data['amount']) * 100),
            auth()->user(),
            filled($data['reference'] ?? null) ? $data['reference'] : null,
            filled($data['note'] ?? null) ? $data['note'] : null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
