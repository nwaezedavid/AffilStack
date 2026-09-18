<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway (Stripe, Flutterwave, ...) implements, so
 * PaymentProcessor, RegistrationController, and BillingController never
 * need to know which gateway a customer picked. Each gateway resolves a
 * browser redirect or a webhook down to the same normalized shape:
 * ['tx_ref', 'remote_id', 'status', 'amount', 'currency', 'meta', 'raw'],
 * plus an optional 'payment_method' key (audit item #2 — see
 * PaymentMethodRecorder) carrying whatever reusable card/account detail that
 * particular gateway/charge exposed, or null when none is available.
 */
interface PaymentGateway
{
    /**
     * The key stored on subscriptions/payment_transactions, and the
     * payment_gateway_settings row this gateway reads its config from.
     */
    public function key(): string;

    public function label(): string;

    public function isEnabled(): bool;

    /**
     * amount_cents/currency are what will actually be charged — almost
     * always the plan's own price/currency unchanged, except a gateway that
     * settles in a different currency (e.g. Paystack converting a USD plan
     * price to NGN kobo) reports the converted figures here so the caller's
     * PaymentTransaction row matches what PaymentProcessor will later see
     * come back from the gateway, instead of assuming the plan's currency.
     *
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    public function initiateSignupCheckout(string $email, string $name, Plan $plan, string $billingCycle, int $pendingSignupId): array;

    /**
     * $overrideAmountCents, when given, charges that figure instead of the
     * plan's own price — the only current use is PlanChangeService's
     * prorated upgrade credit (audit item #2). It's always in the plan's
     * own currency/base units before any gateway-specific conversion (e.g.
     * Paystack's NGN kobo), same as the plan price it replaces.
     *
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    public function initiateCheckout(User $user, Plan $plan, string $billingCycle, ?int $overrideAmountCents = null): array;

    /**
     * Resolve the browser's return-from-checkout redirect into a normalized
     * result, or null if the request doesn't look like this gateway's
     * redirect at all (shouldn't normally happen — routes are gateway-
     * specific — but keeps this safe to call defensively).
     *
     * @return null|array{tx_ref: string, remote_id: string, status: string, amount: float, currency: string, meta: array<string, mixed>, raw: array<string, mixed>}
     */
    public function resolveFromCallback(Request $request): ?array;

    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Resolve a verified webhook payload into the same normalized result,
     * or null if this particular event type isn't one PaymentProcessor
     * needs to act on (the controller should just acknowledge and ignore).
     *
     * @return null|array{tx_ref: string, remote_id: string, status: string, amount: float, currency: string, meta: array<string, mixed>, raw: array<string, mixed>}
     */
    public function resolveFromWebhook(Request $request): ?array;

    /**
     * Ping the gateway's API with the configured credentials to confirm
     * they actually work — powers the admin "Verify credentials" action.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyCredentials(): array;
}
