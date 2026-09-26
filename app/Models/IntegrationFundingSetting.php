<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for Vault, the funding-monitor
 * agent — see Filament: AI Agents > Funding Watch, and
 * App\Services\Integrations\FundingHealthChecker. Tracks two different
 * kinds of "you need to fund this" risk in one place: a real live balance
 * for the one integration that exposes one (HeyGen), and a manual
 * reminder cadence for the ones that don't (Anthropic API usage, Meta ad
 * spend) or that need no external call at all (the payout wallet, which
 * is always just the sum of WalletTransaction).
 */
#[Fillable([
    'heygen_low_balance_threshold', 'heygen_balance_value', 'heygen_balance_checked_at', 'heygen_low_balance_notified_at',
    'anthropic_reminder_days', 'anthropic_reminder_last_acknowledged_at', 'anthropic_reminder_notified_at',
    'meta_ads_reminder_days', 'meta_ads_reminder_last_acknowledged_at', 'meta_ads_reminder_notified_at',
    'wallet_thresholds_cents', 'wallet_currencies_notified',
])]
class IntegrationFundingSetting extends Model
{
    protected function casts(): array
    {
        return [
            'heygen_balance_checked_at' => 'datetime',
            'heygen_low_balance_notified_at' => 'datetime',
            'anthropic_reminder_last_acknowledged_at' => 'datetime',
            'anthropic_reminder_notified_at' => 'datetime',
            'meta_ads_reminder_last_acknowledged_at' => 'datetime',
            'meta_ads_reminder_notified_at' => 'datetime',
            'wallet_thresholds_cents' => 'array',
            'wallet_currencies_notified' => 'array',
        ];
    }

    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' isn't fillable, so the INSERT silently
        // used the next auto-increment value instead — and on MariaDB/MySQL that
        // isn't 1 once any insert has been rolled back — so every call after
        // that created a fresh empty row and saved settings looked lost.
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + [
                // Spelled out explicitly rather than relying on the migration's
                // column defaults: firstOrCreate()'s INSERT only sends the
                // attributes given here, so any column left out comes back as
                // an unset (null) attribute on this in-memory instance even
                // though the database filled in its own default — the two are
                // not the same thing. A brand-new install calling current()
                // for the very first time would otherwise crash the moment
                // isAnthropicReminderDue()/isMetaAdsReminderDue() ran.
                'heygen_low_balance_threshold' => 100,
                'anthropic_reminder_days' => 14,
                'meta_ads_reminder_days' => 14,
                // Both cadences start their clock from creation, not from
                // "never" — otherwise a brand-new install would show every
                // manual reminder as immediately overdue.
                'anthropic_reminder_last_acknowledged_at' => now(),
                'meta_ads_reminder_last_acknowledged_at' => now(),
            ]);
    }

    public function isHeyGenBalanceLow(): bool
    {
        return $this->heygen_balance_value !== null
            && $this->heygen_balance_value < $this->heygen_low_balance_threshold;
    }

    public function isAnthropicReminderDue(): bool
    {
        return $this->isReminderDue($this->anthropic_reminder_last_acknowledged_at, $this->anthropic_reminder_days);
    }

    public function isMetaAdsReminderDue(): bool
    {
        return $this->isReminderDue($this->meta_ads_reminder_last_acknowledged_at, $this->meta_ads_reminder_days);
    }

    protected function isReminderDue(?\DateTimeInterface $lastAcknowledgedAt, int $days): bool
    {
        return $lastAcknowledgedAt === null || $lastAcknowledgedAt->diffInDays(now()) >= $days;
    }

    public function walletThresholdCents(string $currency): int
    {
        return (int) data_get($this->wallet_thresholds_cents, strtoupper($currency), 0);
    }

    public function hasNotifiedWalletLowBalance(string $currency): bool
    {
        return (bool) data_get($this->wallet_currencies_notified, strtoupper($currency), false);
    }
}
