<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Flutterwave Standard Checkout. Credentials come from the admin-entered
 * payment_gateway_settings row (Filament: Billing > Payment Gateways),
 * falling back to the .env values so an existing deployment keeps working
 * even before an admin opens that settings page.
 */
class FlutterwaveGateway implements PaymentGateway
{
    protected PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->settings = PaymentGatewaySetting::forGateway($this->key());
    }

    public function key(): string
    {
        return 'flutterwave';
    }

    public function label(): string
    {
        return 'Flutterwave';
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled;
    }

    protected function baseUrl(): string
    {
        return (string) ($this->settings->credential('base_url') ?: config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3'));
    }

    protected function secretKey(): string
    {
        return (string) ($this->settings->credential('secret_key') ?: config('services.flutterwave.secret_key'));
    }

    protected function secretHash(): string
    {
        return (string) ($this->settings->credential('secret_hash') ?: config('services.flutterwave.secret_hash'));
    }

    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array
    {
        return $this->checkout($email, $name, $plan, $billingCycle, route('registration.callback', ['gateway' => $this->key()]), [
            'pending_signup_id' => $pendingSignupId,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    public function initiateCheckout(User $user, Plan $plan, string $billingCycle): array
    {
        return $this->checkout($user->email, $user->name, $plan, $billingCycle, route('billing.callback', ['gateway' => $this->key()]), [
            'user_id' => $user->id,
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

        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
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

    public function resolveFromCallback(Request $request): ?array
    {
        $txRef = $request->query('tx_ref');
        $transactionId = $request->query('transaction_id');
        $status = $request->query('status');

        if (! $txRef || ! $transactionId || $status !== 'successful') {
            return null;
        }

        return $this->normalize($this->verifyTransaction((string) $transactionId));
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $expected = $this->secretHash();
        $header = $request->header('verif-hash');

        if ($expected === '' || $header === null) {
            return false;
        }

        return hash_equals($expected, $header);
    }

    public function resolveFromWebhook(Request $request): ?array
    {
        $payload = $request->input('data', []);

        if (empty($payload['id'])) {
            return null;
        }

        // Trust the signed webhook only to tell us *which* transaction to
        // look at — always re-verify the actual amount/status
        // server-to-server rather than trusting the webhook body directly.
        return $this->normalize($this->verifyTransaction((string) $payload['id']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function verifyTransaction(string $transactionId): array
    {
        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->get("/transactions/{$transactionId}/verify");

        if ($response->failed()) {
            throw new RuntimeException('Flutterwave verification request failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * @param  array<string, mixed>  $flwData
     * @return array<string, mixed>
     */
    protected function normalize(array $flwData): array
    {
        return [
            'tx_ref' => (string) ($flwData['tx_ref'] ?? ''),
            'remote_id' => (string) ($flwData['id'] ?? ''),
            'status' => (string) ($flwData['status'] ?? ''),
            'amount' => (float) ($flwData['amount'] ?? 0),
            'currency' => (string) ($flwData['currency'] ?? ''),
            'meta' => (array) ($flwData['meta'] ?? []),
            'customer_reference' => (string) ($flwData['customer']['email'] ?? ''),
            'raw' => $flwData,
        ];
    }

    public function verifyCredentials(): array
    {
        $secretKey = $this->secretKey();

        if ($secretKey === '') {
            return ['success' => false, 'message' => 'No secret key configured.'];
        }

        $response = Http::withToken($secretKey)
            ->baseUrl($this->baseUrl())
            ->get('/transactions', ['page' => 1]);

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'Flutterwave rejected the secret key (401 Unauthorized). Double-check it was copied in full and matches the correct mode (test vs. live).'];
        }

        if ($response->failed()) {
            return ['success' => false, 'message' => 'Flutterwave API request failed: HTTP '.$response->status().' — '.Str::limit($response->body(), 200)];
        }

        return ['success' => true, 'message' => 'Connected successfully — Flutterwave accepted the secret key.'];
    }
}
