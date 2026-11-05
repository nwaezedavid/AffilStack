<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's prepaid wallet (feature: customer wallet) — money they top up
 * through checkout and spend on credit packs and plan renewals. Not the
 * affiliate payout wallet (WalletTransaction) nor the API usage wallet
 * (users.api_wallet_balance_cents). balance_minor is in the minor unit of
 * `currency` and only ever changes through CustomerWalletManager, which
 * writes a CustomerWalletTransaction row for every movement.
 */
#[Fillable(['user_id', 'currency', 'balance_minor', 'use_for_renewals'])]
class CustomerWallet extends Model
{
    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'use_for_renewals' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class)->latest('id');
    }

    public function formattedBalance(): string
    {
        return self::format($this->balance_minor, $this->currency);
    }

    /**
     * "$12.50", "₦5,000.00", or "KES 1,200.00" for a currency without a
     * symbol here. Plain PHP so it doesn't need the intl extension.
     */
    public static function format(int $amountMinor, string $currency): string
    {
        $currency = strtoupper($currency);
        $symbols = ['USD' => '$', 'NGN' => '₦', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$', 'GHS' => 'GH₵', 'ZAR' => 'R'];
        $number = number_format(abs($amountMinor) / 100, 2);
        $sign = $amountMinor < 0 ? '-' : '';

        return isset($symbols[$currency]) ? $sign.$symbols[$currency].$number : $sign.$currency.' '.$number;
    }
}
