<?php

namespace App\Services\Flutterwave;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FlutterwaveClient
{
    protected string $baseUrl;

    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->secretKey = (string) config('services.flutterwave.secret_key');
    }

    /**
     * Kick off a Flutterwave Standard checkout for an existing, authenticated
     * user changing or renewing their plan.
     *
     * @return array{link: string, tx_ref: string}
     */
    public function initiateCheckout(User $user, Plan $plan, string $billingCycle): array
    {
        return $this->checkout($user->email, $user->name, $plan, $billingCycle, $this->routeCallback('billing.callback'), [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    /**
     * Kick off a Flutterwave Standard checkout for a brand new account that
     * does not exist yet — the account is only created once this payment
     * verifies (see PaymentProcessor). No card, no account.
     *
     * @return array{link: string, tx_ref: string}
     */
    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array
    {
        return $this->checkout($email, $name, $plan, $billingCycle, $this->routeCallback('registration.callback'), [
            'pending_signup_id' => $pendingSignupId,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{link: string, tx_ref: string}
     */
    protected function checkout(string $email, string $name, Plan $plan, string $billingCycle, string $redirectUrl, array $meta): array
    {
        $txRef = 'affilstack_'.$plan->slug.'_'.$billingCycle.'_'.Str::uuid();
        $amount = $billingCycle === 'yearly' ? $plan->priceYearly() : $plan->priceMonthly();

        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/payments', [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $plan->currency,
                'redirect_url' => $redirectUrl,
                'customer' => [
                    'email' => $email,
                    'name' => $name,
                ],
                'customizations' => [
                    'title' => 'AffilStack — '.$plan->name.' plan',
                    'description' => ucfirst($billingCycle).' subscription',
                ],
                'meta' => array_merge($meta, ['tx_ref' => $txRef]),
            ]);

        if ($response->failed() || data_get($response->json(), 'status') !== 'success') {
            throw new RuntimeException('Flutterwave checkout initiation failed: '.$response->body());
        }

        return [
            'link' => (string) data_get($response->json(), 'data.link'),
            'tx_ref' => $txRef,
        ];
    }

    protected function routeCallback(string $name): string
    {
        return route($name);
    }

    /**
     * Verify a transaction by its Flutterwave transaction ID (returned on
     * the redirect callback as `transaction_id`).
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $transactionId): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->get("/transactions/{$transactionId}/verify");

        if ($response->failed()) {
            throw new RuntimeException('Flutterwave verification request failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Confirm a webhook's `verif-hash` header against the secret hash
     * configured in the Flutterwave dashboard (Settings → Webhooks). This is
     * NOT the API secret key — it's a separate shared secret you set.
     */
    public function verifyWebhookSignature(?string $signatureHeader): bool
    {
        $expected = (string) config('services.flutterwave.secret_hash');

        if ($expected === '' || $signatureHeader === null) {
            return false;
        }

        return hash_equals($expected, $signatureHeader);
    }
}
