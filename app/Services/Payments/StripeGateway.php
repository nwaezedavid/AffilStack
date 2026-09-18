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
 * Stripe Checkout (mode=subscription), driven entirely through Http::asForm()
 * rather than the stripe-php SDK — see FlutterwaveGateway for why this app
 * avoids gateway SDKs. Line items use inline price_data instead of
 * pre-created Stripe Price objects, so plans stay editable from Filament
 * without touching Stripe's dashboard.
 *
 * resolveFromWebhook() only ever resolves the one-time
 * "checkout.session.completed" event, mirroring Flutterwave's
 * callback/webhook shape (initial signup/upgrade checkout). The recurring
 * renewal-cycle events — invoice.paid, invoice.payment_failed,
 * customer.subscription.deleted — are resolved separately by
 * resolveRenewalEvent() and handled by SubscriptionRenewalService (task
 * #85), since they don't fit the tx_ref-based shape PaymentProcessor
 * expects: a renewal has no PaymentTransaction row yet to match against.
 */
class StripeGateway implements PaymentGateway
{
    protected PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->settings = PaymentGatewaySetting::forGateway($this->key());
    }

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled;
    }

    protected function baseUrl(): string
    {
        return (string) ($this->settings->credential('base_url') ?: config('services.stripe.base_url', 'https://api.stripe.com/v1'));
    }

    protected function secretKey(): string
    {
        return (string) ($this->settings->credential('secret_key') ?: config('services.stripe.secret_key'));
    }

    protected function webhookSecret(): string
    {
        return (string) ($this->settings->credential('webhook_secret') ?: config('services.stripe.webhook_secret'));
    }

    protected function client()
    {
        return Http::asForm()->withToken($this->secretKey())->baseUrl($this->baseUrl());
    }

    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array
    {
        return $this->checkout($email, $plan, $billingCycle, route('registration.form', $plan), route('registration.callback', ['gateway' => $this->key()]), [
            'pending_signup_id' => (string) $pendingSignupId,
            'plan_id' => (string) $plan->id,
            'billing_cycle' => $billingCycle,
        ]);
    }

    public function initiateCheckout(User $user, Plan $plan, string $billingCycle, ?int $overrideAmountCents = null): array
    {
        return $this->checkout($user->email, $plan, $billingCycle, route('billing.index'), route('billing.callback', ['gateway' => $this->key()]), [
            'user_id' => (string) $user->id,
            'plan_id' => (string) $plan->id,
            'billing_cycle' => $billingCycle,
        ], $overrideAmountCents);
    }

    /**
     * @param  array<string, string>  $meta
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    protected function checkout(string $email, Plan $plan, string $billingCycle, string $cancelUrl, string $callbackUrl, array $meta, ?int $overrideAmountCents = null): array
    {
        $txRef = 'affilstack_'.$plan->slug.'_'.$billingCycle.'_'.Str::uuid();
        $amountCents = $overrideAmountCents ?? ($billingCycle === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents);
        $meta = array_merge($meta, ['tx_ref' => $txRef]);

        // Stripe's success_url template placeholder must reach Stripe
        // literally — route() already produced the "?gateway=stripe" query,
        // so append the placeholder rather than passing it through route().
        $successUrl = $callbackUrl.'&session_id={CHECKOUT_SESSION_ID}';

        $response = $this->client()->post('/checkout/sessions', [
            'mode' => 'subscription',
            'customer_email' => $email,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $txRef,
            'metadata' => $meta,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => 'AffilStack — '.$plan->name.' plan',
                    ],
                    'recurring' => [
                        'interval' => $billingCycle === 'yearly' ? 'year' : 'month',
                    ],
                ],
            ]],
        ]);

        if ($response->failed() || ! $response->json('id')) {
            throw new RuntimeException('Stripe checkout session creation failed: '.$response->body());
        }

        return [
            'link' => (string) $response->json('url'),
            'tx_ref' => $txRef,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($plan->currency),
        ];
    }

    public function resolveFromCallback(Request $request): ?array
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return null;
        }

        return $this->normalize($this->retrieveSession((string) $sessionId));
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->webhookSecret();
        $header = (string) $request->header('Stripe-Signature');

        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }

    public function resolveFromWebhook(Request $request): ?array
    {
        $payload = $request->json()->all();

        if (($payload['type'] ?? null) !== 'checkout.session.completed') {
            return null;
        }

        $session = $payload['data']['object'] ?? null;

        if (! $session || empty($session['id'])) {
            return null;
        }

        // The signature already proves this body came from Stripe, so —
        // unlike Flutterwave, which we re-verify server-to-server — it's
        // safe (and the documented Stripe-recommended approach) to trust
        // the event payload directly instead of an extra API round-trip.
        return $this->normalize($session);
    }

    /**
     * @return array<string, mixed>
     */
    protected function retrieveSession(string $sessionId): array
    {
        $response = Http::withToken($this->secretKey())
            ->baseUrl($this->baseUrl())
            ->get("/checkout/sessions/{$sessionId}", [
                // Audit item #2 (saved payment methods) — expands enough of
                // the session to read the card's brand/last4/token straight
                // off the callback, no extra API round-trip.
                'expand' => ['payment_intent.payment_method'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe session retrieval failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function normalize(array $session): array
    {
        $paid = ($session['payment_status'] ?? null) === 'paid';
        // Only present when retrieveSession()'s expand actually ran — a
        // webhook's checkout.session.completed payload carries payment_intent
        // as a bare id string, not the expanded object, so this stays null
        // there (the callback path is the one PaymentProcessor's idempotency
        // check normally wins first — see class docblock).
        $paymentMethod = data_get($session, 'payment_intent.payment_method.card');

        return [
            'tx_ref' => (string) (data_get($session, 'metadata.tx_ref') ?? $session['client_reference_id'] ?? ''),
            'remote_id' => (string) (data_get($session, 'payment_intent.id') ?? $session['payment_intent'] ?? $session['id'] ?? ''),
            'status' => $paid ? 'successful' : (string) ($session['payment_status'] ?? 'unpaid'),
            'amount' => ((float) ($session['amount_total'] ?? 0)) / 100,
            'currency' => strtoupper((string) ($session['currency'] ?? '')),
            'meta' => (array) ($session['metadata'] ?? []),
            'customer_reference' => (string) ($session['customer'] ?? ''),
            'subscription_reference' => (string) ($session['subscription'] ?? ''),
            'payment_method' => $paymentMethod ? [
                'type' => 'card',
                'brand' => strtolower((string) ($paymentMethod['brand'] ?? '')),
                'last4' => (string) ($paymentMethod['last4'] ?? ''),
                'exp_month' => (int) ($paymentMethod['exp_month'] ?? 0),
                'exp_year' => (int) ($paymentMethod['exp_year'] ?? 0),
                'token' => (string) data_get($session, 'payment_intent.payment_method.id', ''),
            ] : null,
            'raw' => $session,
        ];
    }

    /**
     * Resolves the recurring-billing webhook events checkout doesn't cover
     * — see class docblock. Deliberately separate from resolveFromWebhook()
     * / the PaymentGateway interface: nothing else needs a gateway to
     * report these, and StripeWebhookController calls this alongside
     * resolveFromWebhook() for every incoming event, so exactly one of the
     * two (or neither, for event types this app doesn't act on) matches
     * any given payload.
     *
     * @return null|array{kind: string, gateway_subscription_id: string, gateway_tx_id?: string, amount?: float, currency?: string, raw: array<string, mixed>}
     */
    public function resolveRenewalEvent(Request $request): ?array
    {
        $payload = $request->json()->all();
        $object = $payload['data']['object'] ?? [];

        return match ($payload['type'] ?? null) {
            'invoice.paid' => $this->resolveInvoicePaid($object),
            'invoice.payment_failed' => $this->resolveInvoiceFailed($object),
            'customer.subscription.deleted' => $this->resolveSubscriptionDeleted($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>|null
     */
    protected function resolveInvoicePaid(array $invoice): ?array
    {
        // Checkout's own first invoice also fires invoice.paid (with
        // billing_reason "subscription_create") at the same time as
        // checkout.session.completed — that one is already handled by
        // resolveFromWebhook()/PaymentProcessor. Only "subscription_cycle"
        // is a genuine recurring renewal.
        if (($invoice['billing_reason'] ?? null) !== 'subscription_cycle') {
            return null;
        }

        if (empty($invoice['subscription'])) {
            return null;
        }

        return [
            'kind' => 'renewed',
            'gateway_subscription_id' => (string) $invoice['subscription'],
            'gateway_tx_id' => (string) ($invoice['id'] ?? ''),
            'amount' => ((float) ($invoice['amount_paid'] ?? 0)) / 100,
            'currency' => strtoupper((string) ($invoice['currency'] ?? '')),
            'raw' => $invoice,
        ];
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>|null
     */
    protected function resolveInvoiceFailed(array $invoice): ?array
    {
        if (empty($invoice['subscription'])) {
            return null;
        }

        return [
            'kind' => 'payment_failed',
            'gateway_subscription_id' => (string) $invoice['subscription'],
            // Carried through so SubscriptionRenewalService::markPastDue()
            // can try the user's other saved cards against this exact
            // invoice before giving up — see retryInvoiceWithPaymentMethod().
            'gateway_invoice_id' => (string) ($invoice['id'] ?? ''),
            'raw' => $invoice,
        ];
    }

    /**
     * Audit item #2's auto-retry: attempts to pay an already-failed renewal
     * invoice using one specific saved card instead of the one Stripe just
     * declined. A success here doesn't update anything itself — it makes
     * Stripe fire its own "invoice.paid" event momentarily after, which
     * StripeGateway::resolveRenewalEvent()/SubscriptionRenewalService::renew()
     * already handle exactly like a normal on-time renewal.
     */
    public function retryInvoiceWithPaymentMethod(string $invoiceId, string $paymentMethodToken): bool
    {
        if ($invoiceId === '' || $paymentMethodToken === '') {
            return false;
        }

        $response = $this->client()->post("/invoices/{$invoiceId}/pay", [
            'payment_method' => $paymentMethodToken,
        ]);

        return $response->successful() && ($response->json('status') === 'paid');
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @return array<string, mixed>|null
     */
    protected function resolveSubscriptionDeleted(array $subscription): ?array
    {
        if (empty($subscription['id'])) {
            return null;
        }

        return [
            'kind' => 'canceled',
            'gateway_subscription_id' => (string) $subscription['id'],
            'raw' => $subscription,
        ];
    }

    /**
     * Audit gap #6: neither gateway handled a refund or chargeback at all —
     * PaymentTransaction::status anticipated "refunded" in its own
     * migration comment but nothing ever set it. Deliberately separate from
     * resolveFromWebhook()/the PaymentGateway interface, same reasoning as
     * resolveRenewalEvent() above: StripeWebhookController checks this
     * alongside the other two for every incoming event.
     *
     * @return null|array{kind: string, gateway_tx_ids: array<int, string>, amount: float, currency: string, raw: array<string, mixed>}
     */
    public function resolveRefundEvent(Request $request): ?array
    {
        $payload = $request->json()->all();
        $object = $payload['data']['object'] ?? [];

        return match ($payload['type'] ?? null) {
            'charge.refunded' => $this->resolveChargeRefunded($object),
            'charge.dispute.created' => $this->resolveChargeDisputeCreated($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $charge
     * @return array<string, mixed>|null
     */
    protected function resolveChargeRefunded(array $charge): ?array
    {
        // A renewal's PaymentTransaction is keyed by its invoice id (see
        // SubscriptionRenewalService::renew()), while an initial payment's
        // is keyed by its payment_intent (see normalize() above) — a
        // refunded charge carries both, so try each rather than assuming
        // which one this transaction was stored under.
        $ids = array_filter([$charge['payment_intent'] ?? null, $charge['invoice'] ?? null]);

        if (empty($ids)) {
            return null;
        }

        return [
            'kind' => 'refunded',
            'gateway_tx_ids' => array_map('strval', array_values($ids)),
            'amount' => ((float) ($charge['amount_refunded'] ?? 0)) / 100,
            'currency' => strtoupper((string) ($charge['currency'] ?? '')),
            'raw' => $charge,
        ];
    }

    /**
     * @param  array<string, mixed>  $dispute
     * @return array<string, mixed>|null
     */
    protected function resolveChargeDisputeCreated(array $dispute): ?array
    {
        $ids = array_filter([$dispute['payment_intent'] ?? null]);

        if (empty($ids)) {
            return null;
        }

        return [
            'kind' => 'charged_back',
            'gateway_tx_ids' => array_map('strval', array_values($ids)),
            'amount' => ((float) ($dispute['amount'] ?? 0)) / 100,
            'currency' => strtoupper((string) ($dispute['currency'] ?? '')),
            'raw' => $dispute,
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
            ->get('/balance');

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'Stripe rejected the secret key (401 Unauthorized). Double-check it was copied in full and matches the correct mode (test vs. live).'];
        }

        if ($response->failed()) {
            return ['success' => false, 'message' => 'Stripe API request failed: HTTP '.$response->status().' — '.Str::limit($response->body(), 200)];
        }

        return ['success' => true, 'message' => 'Connected successfully — Stripe accepted the secret key.'];
    }

    /**
     * Audit item #8's automatic refund policy — actively issues the refund
     * rather than waiting for one, the opposite direction from
     * resolveRefundEvent() above (which only reacts to a refund an admin
     * issued by hand in the Stripe dashboard). gatewayTxId is the
     * payment_intent id normalize() stored as remote_id. A successful call
     * here also eventually fires charge.refunded back at us, but
     * RefundExecutionService already updates the transaction synchronously
     * from this response, so resolveChargeRefunded()'s lookup simply finds
     * nothing left to reverse a second time.
     *
     * @return array{success: bool, message: string, reference?: string, raw: array<string, mixed>}
     */
    public function refund(string $gatewayTxId, int $amountCents): array
    {
        $response = $this->client()->post('/refunds', [
            'payment_intent' => $gatewayTxId,
            'amount' => $amountCents,
        ]);

        $data = $response->json();

        if ($response->failed()) {
            return ['success' => false, 'message' => 'Stripe refund request failed: '.data_get($data, 'error.message', $response->body()), 'raw' => (array) $data];
        }

        return [
            'success' => true,
            'reference' => (string) ($data['id'] ?? $gatewayTxId),
            'message' => 'Refund processed by Stripe.',
            'raw' => (array) $data,
        ];
    }
}
