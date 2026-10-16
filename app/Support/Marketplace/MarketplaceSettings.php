<?php

namespace App\Support\Marketplace;

use App\Models\SiteSetting;

/**
 * Marketplace settings an admin changes in Marketplace → Marketplace
 * settings, falling back to config/marketplace.php.
 */
class MarketplaceSettings
{
    public const ENABLED = 'marketplace_enabled';

    public const REVENUE_SHARE = 'marketplace_revenue_share_percent';

    public const HOLD_DAYS = 'marketplace_earnings_hold_days';

    public const TERMS = 'marketplace_developer_terms';

    public static function enabled(): bool
    {
        return SiteSetting::flag(self::ENABLED, true);
    }

    public static function revenueSharePercent(): float
    {
        $value = SiteSetting::get(self::REVENUE_SHARE);

        return is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : (float) config('marketplace.revenue_share_percent');
    }

    public static function holdDays(): int
    {
        $value = SiteSetting::get(self::HOLD_DAYS);

        return is_numeric($value) ? max(0, (int) $value) : (int) config('marketplace.earnings_hold_days');
    }

    public static function terms(): string
    {
        $terms = trim((string) SiteSetting::get(self::TERMS, ''));

        return $terms !== '' ? $terms : self::defaultTerms();
    }

    public static function defaultTerms(): string
    {
        $share = rtrim(rtrim(number_format(self::revenueSharePercent(), 2), '0'), '.');
        $hold = self::holdDays();

        return <<<TERMS
1. You may only list add-ons you own or have the right to distribute.
2. Every listing and every update is reviewed by AffilStack before customers see it. We may reject, unlist or suspend an add-on that is misleading, unsafe, breaks the law or AffilStack's rules, or misuses customer data.
3. Connected apps may only use the data a customer allows, only to provide the add-on, and must delete it when the customer disconnects. Never sell or share customer data.
4. Paid add-ons are billed monthly by AffilStack. You receive {$share}% of each payment. Earnings can be paid out {$hold} days after each payment, using the payout method on your account. Refunds and chargebacks are taken back from your earnings.
5. You are responsible for supporting your add-on through the support email on your developer profile.
6. You can retire an add-on at any time; customers who already paid keep it until the end of their month.
TERMS;
    }
}
