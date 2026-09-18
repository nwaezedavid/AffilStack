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
     * Audit item #3: Paystack (NGN) is only ever shown to a checkout
     * resolved as Nigerian; Flutterwave/Stripe/PayPal (USD) only to
     * everyone else — on top of each gateway's own admin on/off toggle,
     * never instead of it. See CheckoutCountryResolver.
     *
     * @return array<int, PaymentGateway>
     */
    public function enabledForCountry(string $countryCode): array
    {
        $allowedKeys = $countryCode === 'NG' ? ['paystack'] : ['flutterwave', 'stripe', 'paypal'];

        return array_values(array_filter($this->enabled(), fn (PaymentGateway $gateway) => in_array($gateway->key(), $allowedKeys, true)));
    }
}
