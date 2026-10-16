<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily exchange rates for adding up earnings paid in different
 * currencies. Rates come from the European Central Bank's free daily
 * reference rates (refreshed by fx:refresh), stored as "units per US
 * dollar"; the naira uses the same rate as Paystack payments
 * (ReferralProgram::ngnPerUsd()), since the ECB doesn't publish it.
 * Until the first refresh, rough fallback rates keep totals sensible.
 */
class ExchangeRates
{
    public const SETTING = 'fx_rates';

    public const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /** @var array<string, float> units per USD, used only before the first refresh */
    protected const FALLBACK = [
        'USD' => 1.0, 'EUR' => 0.92, 'GBP' => 0.79, 'CAD' => 1.37, 'AUD' => 1.52, 'CHF' => 0.88,
        'SEK' => 10.6, 'NOK' => 10.8, 'DKK' => 6.9, 'PLN' => 3.95, 'JPY' => 150.0, 'INR' => 84.0,
        'BRL' => 5.5, 'ZAR' => 18.2, 'NZD' => 1.66, 'SGD' => 1.34, 'HKD' => 7.8, 'MXN' => 18.5,
    ];

    /**
     * Units of $currency per US dollar, or null when unknown.
     */
    public static function perUsd(string $currency): ?float
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return 1.0;
        }

        if ($currency === 'NGN') {
            return max(1.0, ReferralProgram::ngnPerUsd());
        }

        $rates = static::rates();

        return isset($rates[$currency]) && $rates[$currency] > 0 ? (float) $rates[$currency] : null;
    }

    public static function toUsdCents(int $cents, string $currency): ?int
    {
        $rate = static::perUsd($currency);

        return $rate === null ? null : (int) round($cents / $rate);
    }

    public static function fromUsdCents(int $usdCents, string $currency): int
    {
        return (int) round($usdCents * (static::perUsd($currency) ?? 1.0));
    }

    /**
     * @return array<string, float>
     */
    public static function rates(): array
    {
        $stored = json_decode((string) SiteSetting::get(self::SETTING, ''), true);

        return is_array($stored['rates'] ?? null) && $stored['rates'] !== [] ? $stored['rates'] : self::FALLBACK;
    }

    public static function updatedOn(): ?string
    {
        $stored = json_decode((string) SiteSetting::get(self::SETTING, ''), true);

        return is_array($stored) ? ($stored['date'] ?? null) : null;
    }

    /**
     * Fetches today's ECB rates. Returns false (keeping the last good
     * rates) if the ECB can't be reached or sends something unexpected.
     */
    public static function refresh(): bool
    {
        try {
            $response = Http::timeout(20)->get(self::ECB_URL);

            if ($response->failed() || ! preg_match_all("/currency=['\"]([A-Z]{3})['\"]\\s+rate=['\"]([0-9.]+)['\"]/", $response->body(), $matches, PREG_SET_ORDER)) {
                return false;
            }

            preg_match("/time=['\"](\\d{4}-\\d{2}-\\d{2})['\"]/", $response->body(), $date);
        } catch (Throwable) {
            return false;
        }

        $perEuro = ['EUR' => 1.0];

        foreach ($matches as [, $code, $rate]) {
            $perEuro[$code] = (float) $rate;
        }

        if (empty($perEuro['USD'])) {
            return false;
        }

        $perUsd = [];

        foreach ($perEuro as $code => $rate) {
            if ($rate > 0) {
                $perUsd[$code] = round($rate / $perEuro['USD'], 6);
            }
        }

        $perUsd['USD'] = 1.0;

        SiteSetting::set(self::SETTING, json_encode(['date' => $date[1] ?? now()->toDateString(), 'rates' => $perUsd]));

        return true;
    }
}
