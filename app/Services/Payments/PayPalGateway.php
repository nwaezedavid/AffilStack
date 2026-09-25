<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PayPal Checkout (Orders API v2) — the international/USD rail added
 * alongside Stripe and Flutterwave (audit item #3). Credentials come from
 * the admin-entered payment_gateway_settings row, falling back to .env,
 * same pattern as the other gateways.
 *
 * PayPal has no "meta" field like Flutterwave/Stripe, so the plan/user/
 * billing-cycle context PaymentProcessor needs back is round-tripped
 * through purchase_units[0].custom_id as a small JSON blob instead.
 */
class PayPalGateway implements PaymentGateway
{
    protected PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->settings = PaymentGatewaySetting::forGateway($this->key());
    }

    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled;
    }

    protected function baseUrl(): string
    {
        return (string) ($this->settings->credential('base_url') ?: config('services.paypal.base_url', 'https://api-m.paypal.com'));
    }

    protected function clientId(): string
    {
        return (string) ($this->settings->credential('client_id') ?: config('services.paypal.client_id'));
    }

    protected function clientSecret(): string
    {
        return (string) ($this->settings->credential('client_secret') ?: config('services.paypal.client_secret'));
    }

    protected function webhookId(): string
    {
        return (string) ($this->settings->credential('webhook_id') ?: config('services.paypal.webhook_id'));
    }

    /**
     * PayPal's client-credentials token is valid for ~9 hours — cached for
     * a conservative 25 minutes so every API call doesn't round-trip an
     * OAuth request, without risking a long-stale token surviving a
     * credential rotation.
     */
    protected function accessToken(): string
    {
        return Cache::remember('paypal:access_token:'.md5($this->clientId()), 1500, function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->baseUrl($this->baseUrl())
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                throw new RuntimeException('PayPal OAuth token request failed: '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    protected function client()
    {
        return Http::withToken($this->accessToken())->baseUrl($this->baseUrl());
    }

    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array
    {
        return $this->checkout(
            $plan,
            $billingCycle,
            route('registration.callback', ['gateway' => $this->key()]),
            route('registration.form', $plan),
            ['pending_signup_id' => $pendingSignupId, 'plan_id' => $plan->id, 'billing_cycle' => $billingCycle]
        );
    }

    public function initiateCheckout(User $user, Plan $plan, string $billingCycle, ?int $overrideAmountCents = null): array
    {
        return $this->checkout(
            $plan,
            $billingCycle,
            route('billing.callback', ['gateway' => $this->key()]),
            route('billing.index'),
            ['user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
            $overrideAmountCents
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    protected function checkout(Plan $plan, string $billingCycle, string $returnUrl, string $cancelUrl, array $meta, ?int $overrideAmountCents = null): array
    {
        $txRef = 'affilstack_'.$plan->slug.'_'.$billingCycle.'_'.Str::uuid();
        $amountCents = $overrideAmountCents ?? ($billingCycle === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents);
        $amount = $amountCents / 100;
        $currency = strtoupper($plan->currency);

        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $txRef,
                'custom_id' => json_encode(array_merge($meta, ['tx_ref' => $txRef])),
                'description' => 'AffilStack — '.$plan->name.' plan ('.ucfirst($billingCycle).')',
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'AffilStack',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('PayPal order creation failed: '.$response->body());
        }

        $approveLink = collect($response->json('links', []))->firstWhere('rel', 'approve');

        if (! $approveLink) {
            throw new RuntimeException('PayPal order creation did not return an approval link: '.$response->body());
        }

        return [
            'link' => (string) $approveLink['href'],
            'tx_ref' => $txRef,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function initiateOneTimeCheckout(User $user, int $amountCents, string $currency, string $description, array $meta): array
    {
        $txRef = 'affilstack_topup_'.Str::uuid();
        $amount = $amountCents / 100;
        $currency = strtoupper($currency);

        // The Orders API call below is already identical in shape to
        // checkout()'s — PayPal has no separate "subscription" primitive
        // here at all, "intent: CAPTURE" is a one-time charge either way —
        // so nothing about this request is actually gateway-specific to
        // top-ups beyond the metadata/description.
        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $txRef,
                'custom_id' => json_encode(array_merge($meta, ['tx_ref' => $txRef])),
                'description' => 'AffilStack — '.$description,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'AffilStack',
                'return_url' => route('billing.callback', ['gateway' => $this->key()]),
                'cancel_url' => route('billing.index'),
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('PayPal one-time order creation failed: '.$response->body());
        }

        $approveLink = collect($response->json('links', []))->firstWhere('rel', 'approve');

        if (! $approveLink) {
            throw new RuntimeException('PayPal one-time order creation did not return an approval link: '.$response->body());
        }

        return [
            'link' => (string) $approveLink['href'],
            'tx_ref' => $txRef,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];
    }

    public function resolveFromCallback(Request $request): ?array
    {
        $orderId = $request->query('token');

        if (! $orderId) {
            return null;
        }

        return $this->normalize($this->captureOrder((string) $orderId));
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = $this->webhookId();

        if ($webhookId === '') {
            return false;
        }

        try {
            $response = $this->client()->post('/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'cert_url' => $request->header('paypal-cert-url'),
                'auth_algo' => $request->header('paypal-auth-algo'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->json()->all(),
            ]);
        } catch (RuntimeException) {
            return false;
        }

        return $response->successful() && data_get($response->json(), 'verification_status') === 'SUCCESS';
    }

    public function resolveFromWebhook(Request $request): ?array
    {
        $eventType = (string) $request->input('event_type', '');

        $orderId = match (true) {
            $eventType === 'CHECKOUT.ORDER.APPROVED' => (string) $request->input('resource.id'),
            in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED'], true) => (string) $request->input('resource.supplementary_data.related_ids.order_id'),
            default => null,
        };

        if (! $orderId) {
            return null;
        }

        return $this->normalize($this->captureOrder($orderId));
    }

    /**
     * Refunds/disputes — mirrors the other gateways' resolveRefundEvent()
     * so the clawback pipeline (audit gap #6 / item #8's refund policy)
     * covers PayPal too. A refund's own resource carries no reference back
     * to our tx_ref, only a "up" link to the capture it refunds — the
     * capture id is what PaymentTransaction::gateway_tx_id was stored as
     * (see normalize() below), so that's what's extracted and matched on.
     *
     * @return null|array{kind: string, gateway_tx_ids: array<int, string>, amount: float, raw: array<string, mixed>}
     */
    public function resolveRefundEvent(Request $request): ?array
    {
        $eventType = (string) $request->input('event_type', '');
        $resource = (array) $request->input('resource', []);

        return match ($eventType) {
            'PAYMENT.CAPTURE.REFUNDED' => $this->resolveCaptureRefunded($resource),
            'CUSTOMER.DISPUTE.CREATED' => $this->resolveDisputeCreated($resource),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function resolveCaptureRefunded(array $resource): ?array
    {
        $captureId = $this->captureIdFromLinks((array) ($resource['links'] ?? []));

        if (! $captureId) {
            return null;
        }

        return [
            'kind' => 'refunded',
            'gateway_tx_ids' => [$captureId],
            'amount' => (float) data_get($resource, 'amount.value', 0),
            'raw' => $resource,
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function resolveDisputeCreated(array $resource): ?array
    {
        $captureId = data_get($resource, 'disputed_transactions.0.seller_transaction_id');

        if (! $captureId) {
            return null;
        }

        return [
            'kind' => 'charged_back',
            'gateway_tx_ids' => [(string) $captureId],
            'amount' => (float) data_get($resource, 'dispute_amount.value', 0),
            'raw' => $resource,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function captureIdFromLinks(array $links): ?string
    {
        $up = collect($links)->firstWhere('rel', 'up');

        if (! $up) {
            return null;
        }

        $segments = explode('/', rtrim((string) ($up['href'] ?? ''), '/'));

        return end($segments) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function captureOrder(string $orderId): array
    {
        $existing = $this->client()->get("/v2/checkout/orders/{$orderId}");

        if ($existing->successful() && $existing->json('status') === 'COMPLETED') {
            return $existing->json();
        }

        $response = $this->client()->post("/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            throw new RuntimeException('PayPal order capture failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function normalize(array $order): array
    {
        $capture = data_get($order, 'purchase_units.0.payments.captures.0', []);
        $status = (string) ($order['status'] ?? '');

        return [
            'tx_ref' => (string) data_get($order, 'purchase_units.0.reference_id', ''),
            'remote_id' => (string) ($capture['id'] ?? $order['id'] ?? ''),
            'status' => $status === 'COMPLETED' ? 'successful' : $status,
            'amount' => (float) ($capture['amount']['value'] ?? data_get($order, 'purchase_units.0.amount.value', 0)),
            'currency' => strtoupper((string) ($capture['amount']['currency_code'] ?? data_get($order, 'purchase_units.0.amount.currency_code', ''))),
            'meta' => json_decode((string) data_get($order, 'purchase_units.0.custom_id', '{}'), true) ?: [],
            'customer_reference' => (string) data_get($order, 'payer.email_address', ''),
            'reference' => (string) ($order['id'] ?? ''),
            // Audit item #2 (saved payment methods) — PayPal has no reusable
            // card token here (that needs the separate Vault API this app
            // doesn't use), but the payer's own PayPal account is itself the
            // "payment method" a returning user picks again at checkout.
            'payment_method' => data_get($order, 'payer.email_address') ? [
                'type' => 'paypal',
                'label' => (string) data_get($order, 'payer.email_address'),
                'gateway_customer_id' => (string) data_get($order, 'payer.payer_id', ''),
            ] : null,
            'raw' => $order,
        ];
    }

    /**
     * The rest-of-world side of the affiliate payout wallet (audit item
     * #5) — PayPal's Payouts API, a single-item "batch" per call rather
     * than the real batch payout it supports, since disbursement here
     * always happens one ReferralPayout at a time from an admin action.
     *
     * @return array{success: bool, reference?: string, message: string, raw: array<string, mixed>}
     */
    public function payout(string $recipientEmail, int $amountCents, string $currency, string $reference, string $note): array
    {
        $response = $this->client()->post('/v1/payments/payouts', [
            'sender_batch_header' => [
                'sender_batch_id' => $reference,
                'email_subject' => 'You have a payout from AffilStack',
            ],
            'items' => [[
                'recipient_type' => 'EMAIL',
                'amount' => ['value' => number_format($amountCents / 100, 2, '.', ''), 'currency' => strtoupper($currency)],
                'receiver' => $recipientEmail,
                'note' => $note,
                'sender_item_id' => $reference,
            ]],
        ]);

        $data = $response->json();

        if ($response->failed()) {
            return ['success' => false, 'message' => 'PayPal payout request failed: '.$response->body(), 'raw' => (array) $data];
        }

        return [
            'success' => true,
            // The batch payout id — the item itself settles asynchronously,
            // same caveat as FlutterwaveGateway::transfer().
            'reference' => (string) data_get($data, 'batch_header.payout_batch_id', $reference),
            'message' => 'Payout accepted by PayPal — batch status: '.data_get($data, 'batch_header.batch_status', 'PENDING').'.',
            'raw' => (array) $data,
        ];
    }

    /**
     * PayPal has no reusable-card/vault mechanism integrated in this app —
     * every PayPal "payment method" saved here (see normalize() below) is
     * just a payer email/account, not a chargeable token, so there is
     * nothing to charge again without the payer approving a brand new order
     * in their browser. Always returns unsupported rather than pretending
     * to try — see PaymentGateway::chargeSavedToken()'s docblock for why
     * that's the contract every caller can rely on.
     *
     * @return array{success: bool, message: string}
     */
    public function chargeSavedToken(PaymentMethod $method, int $amountCents, string $currency, string $description): array
    {
        return [
            'success' => false,
            'message' => 'PayPal doesn\'t support automatic recharges on this platform — top up manually, or add a card via Stripe, Flutterwave, or Paystack to enable auto-recharge.',
        ];
    }

    public function verifyCredentials(): array
    {
        if ($this->clientId() === '' || $this->clientSecret() === '') {
            return ['success' => false, 'message' => 'Client ID and/or secret not configured.'];
        }

        Cache::forget('paypal:access_token:'.md5($this->clientId()));

        try {
            $this->accessToken();
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => 'PayPal rejected the client credentials. Double-check the Client ID and Secret were copied in full and match the correct mode (sandbox vs. live). Raw error: '.Str::limit($e->getMessage(), 150)];
        }

        return ['success' => true, 'message' => 'Connected successfully — PayPal accepted the client credentials.'];
    }

    /**
     * Audit item #8's automatic refund policy — actively issues the refund
     * rather than waiting for one, the opposite direction from
     * resolveRefundEvent() above (which only reacts to a refund an admin
     * issued by hand in the PayPal dashboard). $captureId is the capture id
     * normalize() stored as remote_id — PayPal refunds a specific capture,
     * not the order itself.
     *
     * @return array{success: bool, message: string, reference?: string, raw: array<string, mixed>}
     */
    public function refund(string $captureId, int $amountCents, string $currency): array
    {
        $response = $this->client()->post("/v2/payments/captures/{$captureId}/refund", [
            'amount' => [
                'value' => number_format($amountCents / 100, 2, '.', ''),
                'currency_code' => strtoupper($currency),
            ],
        ]);

        $data = $response->json();

        if ($response->failed() || ! in_array(data_get($data, 'status'), ['COMPLETED', 'PENDING'], true)) {
            return ['success' => false, 'message' => 'PayPal refund request failed: '.$response->body(), 'raw' => (array) $data];
        }

        return [
            'success' => true,
            'reference' => (string) ($data['id'] ?? $captureId),
            'message' => 'Refund processed by PayPal.',
            'raw' => (array) $data,
        ];
    }
}
