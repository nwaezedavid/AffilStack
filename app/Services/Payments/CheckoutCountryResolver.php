<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Decides which country a checkout is happening from (audit item #3 —
 * Paystack/Naira is shown only to Nigerians, Stripe/Flutterwave/PayPal only
 * to everyone else). Uses ip-api.com's free tier, the same no-key
 * geolocation service already used for cloaked-link click tracking (see
 * ResolveLinkClickGeo) — but resolved synchronously here since the
 * pricing/checkout page has to decide what to show before it renders,
 * unlike a redirect that can fill in the country after the fact.
 *
 * A session override always wins, set via CheckoutCountryController — auto-
 * detection is a best guess (VPNs, corporate proxies, and ip-api's own
 * misses all happen), so anyone it gets wrong needs a one-click way out.
 */
class CheckoutCountryResolver
{
    public function resolve(Request $request): string
    {
        // hasSession() guards a request built outside the normal HTTP
        // kernel (a console context, or a bare Request in a test) that
        // never got the session middleware's store attached.
        $override = $request->hasSession() ? $request->session()->get('checkout_country') : null;

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $ip = $request->ip();

        if (! $ip || $this->isPrivateOrLocal($ip)) {
            return 'US';
        }

        return Cache::remember('checkout_country:'.$ip, now()->addHours(6), function () use ($ip) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode',
                ]);

                if ($response->successful() && $response->json('status') === 'success' && $response->json('countryCode')) {
                    return (string) $response->json('countryCode');
                }
            } catch (Throwable) {
                // Best-effort, same reasoning as ResolveLinkClickGeo — never
                // let a geolocation hiccup block checkout from rendering.
            }

            return 'US';
        });
    }

    public function isNigeria(Request $request): bool
    {
        return $this->resolve($request) === 'NG';
    }

    protected function isPrivateOrLocal(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
