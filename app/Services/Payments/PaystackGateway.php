<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paystack Standard Checkout — the Nigeria/naira rail (audit item #3).
 * Credentials come from the admin-entered payment_gateway_settings row
 * (Filament: Billing > Payment Gateways), falling back to .env, same
 * pattern as FlutterwaveGateway/StripeGateway.
 *
 * Every plan is priced in USD (Plan::currency), but Paystack settles
 * Nigerian cards in NGN — there's no live FX API in this app, so the
 * conversion rate is an admin-editable number (see usd_to_ngn_rate below)
 * rather than something fetched at checkout time. Keep it current from the
 * Payment Gateways page.
 */
class PaystackGateway implements PaymentGateway
{
    protected PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->settings = PaymentGatewaySetting::forGateway($this->key());
    }

    public function key(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack';
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled;
    }

    protected function baseUrl(): string
    {
        return (string) ($this->settings->credential('base_url') ?: config('services.paystack.base_url', 'https://api.paystack.co'));
    }

    protected function secretKey(): string
    {
        return (string) ($this->settings->credential('secret_key') ?: config('services.paystack.secret_key'));
    }

    protected function exchangeRate(): float
    {
        return (float) ($this->settings->credential('usd_to_ngn_rate') ?: config('services.paystack.usd_to_ngn_rate', 1600));
    }

    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array
    {
        return $this->checkout($email, $plan, $billingCycle, route('registration.callback', ['gateway' => $this->key()]), [
            'pending_signup_id' => $pendingSignupId,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    public function initiateCheckout(User $user, Plan $plan, string $billingCycle, ?int $overrideAmountCents = null): array
    {
        return $this->checkout($user->email, $plan, $billingCycle, route('billing.callback', ['gateway' => $this->key()]), [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ], $overrideAmountCents);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    protected function checkout(string $email, Plan $plan, string $billingCycle, string $callbackUrl, array $meta, ?int $overrideAmountCents = null): array
    {
        $txRef = 'affilstack_'.$plan->slug.'_'.$billingCycle.'_'.Str::uuid();
        $usdAmountCents = $overrideAmountCents ?? ($billingCycle === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents);
        $amountKobo = (int) round(($usdAmountCents / 100) * $this->exchangeRate() * 100);

        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->post('/transaction/initialize', [
                'email' => $email,
                'amount' => $amountKobo,
                'currency' => 'NGN',
                'reference' => $txRef,
                'callback_url' => $callbackUrl,
                'metadata' => array_merge($meta, ['tx_ref' => $txRef]),
            ]);

        if ($response->failed() || data_get($response->json(), 'status') !== true) {
            throw new RuntimeException('Paystack checkout initiation failed: '.$response->body());
        }

        return [
            'link' => (string) data_get($response->json(), 'data.authorization_url'),
            'tx_ref' => $txRef,
            'amount_cents' => $amountKobo,
            'currency' => 'NGN',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function initiateOneTimeCheckout(User $user, int $amountCents, string $currency, string $description, array $meta): array
    {
        $txRef = 'affilstack_topup_'.Str::uuid();
        // $amountCents arrives in the package's own currency (USD, same as
        // every CreditPackage price) — converted to NGN kobo exactly like
        // checkout() does for a plan, since Paystack always settles
        // Nigerian cards in naira regardless of what's being purchased.
        $amountKobo = (int) round(($amountCents / 100) * $this->exchangeRate() * 100);

        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->post('/transaction/initialize', [
                'email' => $user->email,
                'amount' => $amountKobo,
                'currency' => 'NGN',
                'reference' => $txRef,
                'callback_url' => route('billing.callback', ['gateway' => $this->key()]),
                'metadata' => array_merge($meta, ['tx_ref' => $txRef]),
            ]);

        if ($response->failed() || data_get($response->json(), 'status') !== true) {
            throw new RuntimeException('Paystack one-time checkout initiation failed: '.$response->body());
        }

        return [
            'link' => (string) data_get($response->json(), 'data.authorization_url'),
            'tx_ref' => $txRef,
            'amount_cents' => $amountKobo,
            'currency' => 'NGN',
        ];
    }

    public function resolveFromCallback(Request $request): ?array
    {
        $reference = $request->query('reference') ?: $request->query('trxref');

        if (! $reference) {
            return null;
        }

        return $this->normalize($this->verifyTransaction((string) $reference));
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->secretKey();
        $header = $request->header('x-paystack-signature');

        if ($secret === '' || $header === null) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, (string) $header);
    }

    public function resolveFromWebhook(Request $request): ?array
    {
        if (($request->input('event') ?? null) !== 'charge.success') {
            return null;
        }

        $reference = $request->input('data.reference');

        if (! $reference) {
            return null;
        }

        // Trust the signed webhook only to say *which* transaction to look
        // at — always re-verify amount/status server-to-server, same
        // reasoning as FlutterwaveGateway.
        return $this->normalize($this->verifyTransaction((string) $reference));
    }

    /**
     * Refunds/disputes — mirrors FlutterwaveGateway::resolveRefundEvent()/
     * StripeGateway::resolveRefundEvent() so the clawback pipeline (audit
     * gap #6 / item #8's refund policy) covers Paystack too.
     *
     * @return null|array{kind: string, gateway_references: array<int, string>, amount: float, raw: array<string, mixed>}
     */
    public function resolveRefundEvent(Request $request): ?array
    {
        $payload = $request->json()->all();

        return match ($payload['event'] ?? null) {
            'refund.processed' => $this->resolveRefundProcessed((array) ($payload['data'] ?? [])),
            'charge.dispute.create' => $this->resolveDisputeCreated((array) ($payload['data'] ?? [])),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveRefundProcessed(array $data): ?array
    {
        $reference = $data['transaction_reference'] ?? data_get($data, 'transaction.reference');

        if (! $reference) {
            return null;
        }

        return [
            'kind' => 'refunded',
            'gateway_references' => [(string) $reference],
            'amount' => ((float) ($data['amount'] ?? 0)) / 100,
            'raw' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveDisputeCreated(array $data): ?array
    {
        $reference = data_get($data, 'transaction.reference');

        if (! $reference) {
            return null;
        }

        return [
            'kind' => 'charged_back',
            'gateway_references' => [(string) $reference],
            'amount' => ((float) ($data['amount'] ?? 0)) / 100,
            'raw' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->get('/transaction/verify/'.rawurlencode($reference));

        if ($response->failed()) {
            throw new RuntimeException('Paystack verification request failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        $status = (string) ($data['status'] ?? '');

        return [
            'tx_ref' => (string) ($data['reference'] ?? ''),
            'remote_id' => (string) ($data['id'] ?? ''),
            'status' => $status === 'success' ? 'successful' : $status,
            'amount' => ((float) ($data['amount'] ?? 0)) / 100,
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'meta' => (array) ($data['metadata'] ?? []),
            'customer_reference' => (string) data_get($data, 'customer.email', ''),
            // Paystack's own reference IS our tx_ref (we set it at
            // initialize time) — stored as gateway_reference too so a later
            // refund/dispute webhook (which only reports this reference,
            // not our numeric remote_id) can still be matched. See
            // resolveRefundEvent() above and RefundProcessor::process().
            'reference' => (string) ($data['reference'] ?? ''),
            // Audit item #2 (saved payment methods) — present whenever the
            // card issuer allows reuse; authorization_code is Paystack's
            // reusable charge token (see PaymentMethodRecorder).
            'payment_method' => (data_get($data, 'authorization.reusable') && data_get($data, 'authorization.authorization_code')) ? [
                'type' => 'card',
                'brand' => strtolower((string) data_get($data, 'authorization.card_type', '')),
                'last4' => (string) data_get($data, 'authorization.last4', ''),
                'exp_month' => (int) data_get($data, 'authorization.exp_month', 0),
                'exp_year' => (int) data_get($data, 'authorization.exp_year', 0),
                'token' => (string) data_get($data, 'authorization.authorization_code'),
            ] : null,
            'raw' => $data,
        ];
    }

    /**
     * @return array{success: bool, message: string, reference?: string, raw?: array<string, mixed>}
     */
    public function chargeSavedToken(PaymentMethod $method, int $amountCents, string $currency, string $description): array
    {
        if ($method->gateway_token === null || $method->gateway_token === '') {
            return ['success' => false, 'message' => 'No saved Paystack authorization code on file.'];
        }

        // Paystack always settles in NGN regardless of what's being charged
        // — same conversion checkout()/initiateOneTimeCheckout() already
        // apply, since $amountCents/$currency here arrive in the wallet's
        // own USD terms, not kobo.
        $amountKobo = strtoupper($currency) === 'NGN'
            ? $amountCents
            : (int) round(($amountCents / 100) * $this->exchangeRate() * 100);

        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->post('/transaction/charge_authorization', [
                'authorization_code' => $method->gateway_token,
                'email' => $method->user?->email,
                'amount' => $amountKobo,
                'currency' => 'NGN',
            ]);

        $data = $response->json();
        $status = data_get($data, 'data.status');

        if ($response->failed() || data_get($data, 'status') !== true || $status !== 'success') {
            return [
                'success' => false,
                'message' => data_get($data, 'data.gateway_response', data_get($data, 'message', 'Paystack declined the saved card: '.$response->body())),
                'raw' => (array) $data,
            ];
        }

        return [
            'success' => true,
            'reference' => (string) data_get($data, 'data.reference', ''),
            'message' => 'Charged successfully.',
            'raw' => (array) $data,
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
            ->get('/transaction', ['perPage' => 1]);

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'Paystack rejected the secret key (401 Unauthorized). Double-check it was copied in full and matches the correct mode (test vs. live).'];
        }

        if ($response->failed()) {
            return ['success' => false, 'message' => 'Paystack API request failed: HTTP '.$response->status().' — '.Str::limit($response->body(), 200)];
        }

        return ['success' => true, 'message' => 'Connected successfully — Paystack accepted the secret key.'];
    }

    /**
     * Audit item #8's automatic refund policy — actively issues the refund
     * rather than waiting for one, the opposite direction from
     * resolveRefundEvent() above (which only reacts to a refund an admin
     * issued by hand in the Paystack dashboard). $reference is Paystack's
     * own transaction reference — normalize() stores it as both tx_ref and
     * gateway_reference, either of which the refund endpoint accepts.
     * amount is in kobo, already the unit PaymentTransaction::amount_cents
     * stores this gateway's amounts in — no conversion needed here.
     *
     * @return array{success: bool, message: string, reference?: string, raw: array<string, mixed>}
     */
    public function refund(string $reference, int $amountKobo): array
    {
        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->post('/refund', [
                'transaction' => $reference,
                'amount' => $amountKobo,
            ]);

        $data = $response->json();

        if ($response->failed() || data_get($data, 'status') !== true) {
            return ['success' => false, 'message' => data_get($data, 'message', 'Paystack refund request failed: '.$response->body()), 'raw' => (array) $data];
        }

        return [
            'success' => true,
            'reference' => (string) data_get($data, 'data.transaction_reference', $reference),
            'message' => 'Refund accepted by Paystack.',
            'raw' => (array) $data,
        ];
    }
}
