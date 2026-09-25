<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Turns a gateway's normalized 'payment_method' result (see PaymentGateway's
 * interface docblock) into a saved, reusable PaymentMethod row — audit item
 * #2 ("users should be able to save more than one payment method"). Never
 * asks for card details directly: every row here is captured passively from
 * a successful charge's own gateway response, deduplicated by the token/
 * label the gateway itself considers unique for that account.
 */
class PaymentMethodRecorder
{
    /**
     * @param  array<string, mixed>  $result  the gateway's full normalized checkout result
     */
    public function record(User $user, string $gateway, array $result): ?PaymentMethod
    {
        $data = $result['payment_method'] ?? null;

        if (! $data) {
            return null;
        }

        $type = (string) ($data['type'] ?? 'card');

        $identity = $type === 'paypal'
            ? ['user_id' => $user->id, 'gateway' => $gateway, 'type' => $type, 'label' => $data['label'] ?? null]
            : ['user_id' => $user->id, 'gateway' => $gateway, 'type' => $type, 'gateway_token' => $data['token'] ?? null];

        // gateway_token is encrypted at rest, so it can't be matched via a
        // WHERE clause on the ciphertext — dedupe card methods in PHP
        // instead, by the plaintext token, and fall back to the (unencrypted)
        // label for PayPal's account-based "method".
        $existing = $type === 'paypal'
            ? PaymentMethod::where('user_id', $user->id)->where('gateway', $gateway)->where('type', $type)->where('label', $data['label'] ?? null)->first()
            : PaymentMethod::where('user_id', $user->id)->where('gateway', $gateway)->where('type', $type)->get()
                ->first(fn (PaymentMethod $method) => $method->gateway_token === ($data['token'] ?? null));

        $attributes = array_filter([
            'brand' => $data['brand'] ?? null,
            'last4' => $data['last4'] ?? null,
            'exp_month' => $data['exp_month'] ?? null,
            'exp_year' => $data['exp_year'] ?? null,
            'label' => $data['label'] ?? null,
            // Flutterwave-only today (see FlutterwaveGateway::normalize()/
            // extractCountryCode()) — needed for chargeSavedToken()'s
            // tokenized-charge call. null for every other gateway's cards,
            // which don't need it.
            'country' => $data['country'] ?? null,
            'gateway_customer_id' => $data['gateway_customer_id'] ?? null,
            'gateway_token' => $data['token'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0);

        if ($existing) {
            $existing->update(array_merge($attributes, ['last_used_at' => now()]));

            return $existing;
        }

        $isFirst = ! PaymentMethod::where('user_id', $user->id)->exists();

        return PaymentMethod::create(array_merge($identity, $attributes, [
            // The very first saved method for a user becomes their default
            // automatically — every one after that is added alongside it.
            'is_default' => $isFirst,
            'last_used_at' => now(),
        ]));
    }
}
