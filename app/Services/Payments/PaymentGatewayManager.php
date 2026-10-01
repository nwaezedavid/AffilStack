<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * Resolves a PaymentGateway by key. Add a new gateway by registering it in
 * $gateways below and creating its payment_gateway_settings row from the
 * admin Payment Gateways page.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    protected array $gateways = [
        'flutterwave' => FlutterwaveGateway::class,
        'stripe' => StripeGateway::class,
        'paystack' => PaystackGateway::class,
        'paypal' => PayPalGateway::class,
    ];

    public function get(string $key): PaymentGateway
    {
        if (! isset($this->gateways[$key])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return app($this->gateways[$key]);
    }

    /**
     * @return array<int, PaymentGateway>
     */
    public function all(): array
    {
        return array_map(fn (string $key) => $this->get($key), array_keys($this->gateways));
    }

    /**
     * @return array<int, PaymentGateway>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), fn (PaymentGateway $gateway) => $gateway->isEnabled()));
    }

    /**
     * Gateways that charge in naira. Every other gateway charges the plan's
     * own currency (US dollars).
     *
     * @var array<int, string>
     */
    public const NAIRA_GATEWAYS = ['paystack'];

    /**
     * Which enabled gateways a checkout may use. Everyone can pay in US
     * dollars through any enabled dollar gateway (Flutterwave, Stripe,
     * PayPal) — Nigeria included. A checkout resolved as Nigerian also gets
     * Paystack (naira), listed first, but only while an admin has Paystack
     * switched on. See CheckoutCountryResolver.
     *
     * @return array<int, PaymentGateway>
     */
    public function enabledForCountry(string $countryCode): array
    {
        $enabled = $this->enabled();

        $dollar = array_filter($enabled, fn (PaymentGateway $gateway) => ! $this->chargesInNaira($gateway));
        $naira = $countryCode === 'NG'
            ? array_filter($enabled, fn (PaymentGateway $gateway) => $this->chargesInNaira($gateway))
            : [];

        return array_values(array_merge($naira, $dollar));
    }

    public function chargesInNaira(PaymentGateway $gateway): bool
    {
        return in_array($gateway->key(), self::NAIRA_GATEWAYS, true);
    }

    /**
     * Whether naira checkout exists at all right now — drives the
     * "Paying from Nigeria? Switch to Naira" link, which is pointless while
     * Paystack is off.
     */
    public function nairaAvailable(): bool
    {
        return collect($this->enabled())->contains(fn (PaymentGateway $gateway) => $this->chargesInNaira($gateway));
    }

    /**
     * The wording a customer picks from, e.g. "Pay in Naira (₦) — Paystack".
     */
    public function optionLabel(PaymentGateway $gateway): string
    {
        return $this->chargesInNaira($gateway)
            ? 'Pay in Naira (₦) — '.$gateway->label()
            : 'Pay in US dollars ($) — '.$gateway->label();
    }
}
