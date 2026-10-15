<?php

namespace App\Support;

use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\PaystackGateway;
use Throwable;

/**
 * The two referral programs and the rules an admin can change for them
 * (Partners → Affiliate Program):
 *
 * - the customer program — every paying customer's Refer & Earn page;
 * - the partner program — approved affiliate-only accounts on the
 *   Partner Portal (affiliate.affilstack.com).
 *
 * Both start from config('referrals.commission_rate') and can be set
 * separately. Money helpers keep naira and dollar amounts readable.
 */
class ReferralProgram
{
    public const CUSTOMER_RATE_SETTING = 'referral_commission_percent_customers';

    public const PARTNER_RATE_SETTING = 'referral_commission_percent_partners';

    public const MINIMUM_PAYOUT_SETTING = 'referral_minimum_payout_usd';

    public const LEADERBOARD_SETTING = 'partner_leaderboard_enabled';

    public static function defaultPercent(): float
    {
        return round((float) config('referrals.commission_rate') * 100, 2);
    }

    public static function customerPercent(): float
    {
        return static::percent(self::CUSTOMER_RATE_SETTING);
    }

    public static function partnerPercent(): float
    {
        return static::percent(self::PARTNER_RATE_SETTING);
    }

    /**
     * The commission rate (a fraction, e.g. 0.2) earned by this referrer.
     */
    public static function rateFor(User $referrer): float
    {
        return ($referrer->isAffiliateOnly() ? static::partnerPercent() : static::customerPercent()) / 100;
    }

    public static function percentFor(User $referrer): float
    {
        return $referrer->isAffiliateOnly() ? static::partnerPercent() : static::customerPercent();
    }

    public static function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2), '0'), '.').'%';
    }

    /**
     * Minimum payout in US cents.
     */
    public static function minimumPayoutUsdCents(): int
    {
        $usd = SiteSetting::get(self::MINIMUM_PAYOUT_SETTING);

        return is_numeric($usd) && (float) $usd >= 0
            ? (int) round((float) $usd * 100)
            : (int) config('referrals.minimum_payout_cents');
    }

    /**
     * Minimum payout in a currency's own minor units: naira balances are
     * converted at the Paystack rate, other currencies use the dollar
     * figure as-is.
     */
    public static function minimumPayoutCents(string $currency): int
    {
        $cents = static::minimumPayoutUsdCents();

        return strtoupper($currency) === 'NGN' ? (int) round($cents * static::ngnPerUsd()) : $cents;
    }

    public static function leaderboardEnabled(): bool
    {
        return SiteSetting::flag(self::LEADERBOARD_SETTING, false);
    }

    public static function cookieDays(): int
    {
        return (int) config('referrals.cookie_days');
    }

    public static function ngnPerUsd(): float
    {
        try {
            return app(PaystackGateway::class)->ngnPerUsd();
        } catch (Throwable) {
            return (float) config('services.paystack.usd_to_ngn_rate', 1600);
        }
    }

    /**
     * An amount in US cents, for adding up earnings across currencies.
     */
    public static function toUsdCents(int $cents, string $currency): int
    {
        return strtoupper($currency) === 'NGN' ? (int) round($cents / max(1, static::ngnPerUsd())) : $cents;
    }

    public static function money(int $cents, string $currency = 'USD'): string
    {
        $currency = strtoupper($currency);
        $symbol = match ($currency) {
            'USD' => '$',
            'NGN' => '₦',
            'EUR' => '€',
            'GBP' => '£',
            default => null,
        };
        $amount = number_format($cents / 100, 2);

        return $symbol !== null ? ($cents < 0 ? '-' : '').$symbol.ltrim($amount, '-') : $amount.' '.$currency;
    }

    public static function methodLabel(?string $method): string
    {
        return (string) config('referrals.payout_method_labels.'.$method, $method ?? '—');
    }

    /**
     * Pages on the public website a campaign link or banner ad can land
     * on, as path => label.
     *
     * @return array<string, string>
     */
    public static function landingPages(): array
    {
        $pages = [
            '/pricing' => 'Pricing & plans',
            '/' => 'Homepage',
            '/features' => 'All features',
        ];

        try {
            foreach (FeaturePage::publishedList() as $page) {
                $pages['/features/'.$page->slug] = 'Feature: '.$page->name;
            }
        } catch (Throwable) {
            // The list of features is a nice-to-have here.
        }

        return $pages;
    }

    protected static function percent(string $setting): float
    {
        $value = SiteSetting::get($setting);

        return is_numeric($value) && (float) $value >= 0 && (float) $value <= 100
            ? round((float) $value, 2)
            : static::defaultPercent();
    }
}
