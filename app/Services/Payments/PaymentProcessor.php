<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agents\SamAgentService;
use App\Services\ApiWallet\ApiWalletManager;
use App\Services\Credits\CreditManager;
use App\Services\Referrals\ReferralService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * The one place that turns a verified, gateway-normalized payment result
 * into an active subscription + credit grant. Called from both the browser
 * redirect callback and the server-to-server webhook, for either gateway,
 * so it must be idempotent — either caller might arrive first, or the
 * webhook might retry.
 */
class PaymentProcessor
{
    public function __construct(
        protected CreditManager $credits,
        protected ReferralService $referrals,
        protected SamAgentService $sam,
        protected PaymentMethodRecorder $paymentMethods,
        protected ApiWalletManager $apiWallet,
    ) {}

    /**
     * @param  array{tx_ref: string, remote_id: string, status: string, amount: float, currency: string, meta: array<string, mixed>, customer_reference?: string, subscription_reference?: string, raw: array<string, mixed>}  $result
     */
    public function process(string $gateway, array $result): ?PaymentTransaction
    {
        $txRef = $result['tx_ref'];
        $transaction = PaymentTransaction::where('tx_ref', $txRef)->first();

        if (! $transaction) {
            Log::warning('Payment webhook/callback for unknown tx_ref', ['gateway' => $gateway, 'tx_ref' => $txRef]);

            return null;
        }

        // Already processed by the other caller (webhook vs. redirect race) — no-op.
        if ($transaction->status === 'successful') {
            return $transaction;
        }

        $expectedAmount = $transaction->amount_cents / 100;
        $isSuccessful = in_array($result['status'], ['successful', 'succeeded', 'complete', 'paid'], true);

        if (! $isSuccessful || $result['amount'] < $expectedAmount || $result['currency'] !== $transaction->currency) {
            $transaction->update([
                'status' => 'failed',
                'gateway' => $gateway,
                'gateway_tx_id' => $result['remote_id'] ?: $transaction->gateway_tx_id,
                'raw_payload' => $result['raw'],
                'processed_at' => now(),
            ]);

            Log::warning('Payment failed verification', [
                'gateway' => $gateway, 'tx_ref' => $txRef, 'status' => $result['status'],
                'expected' => $expectedAmount, 'actual' => $result['amount'],
            ]);

            return $transaction;
        }

        $transaction->update([
            'status' => 'successful',
            'gateway' => $gateway,
            'gateway_tx_id' => $result['remote_id'],
            // Only Flutterwave provides this (its flw_ref) — needed to
            // match a later chargeback webhook back to this transaction.
            // See FlutterwaveGateway::resolveRefundEvent().
            'gateway_reference' => $result['reference'] ?? null,
            'raw_payload' => $result['raw'],
            'processed_at' => now(),
        ]);

        if ($transaction->type === 'signup') {
            $transaction = $this->completeSignup($transaction);
        }

        if ($transaction->type === 'subscription') {
            $this->activateSubscription($transaction, $gateway, $result);
        }

        if ($transaction->type === 'credit_topup') {
            $this->grantCreditTopup($transaction, $gateway, $result);
        }

        if ($transaction->type === 'api_wallet_topup') {
            $this->grantApiWalletTopup($transaction, $gateway, $result);
        }

        return $transaction;
    }

    /**
     * API usage prepay wallet — a plain "pay $X, wallet balance goes up by
     * $X" top-up (see ApiWalletController::checkout()), no package/bonus
     * tiers to look up unlike credit_topup's CreditPackage. Also records
     * the payment method exactly like every other purchase type: this is
     * how a card actually gets onto file for a user to later pick as their
     * auto-recharge method (see User::apiWalletPaymentMethod()) — "bind
     * your card" IS "pay with it once," there's no separate save-a-card
     * flow in this app.
     *
     * @param  array<string, mixed>  $result
     */
    protected function grantApiWalletTopup(PaymentTransaction $transaction, string $gateway, array $result): void
    {
        if (! $transaction->user_id) {
            Log::error('API wallet top-up payment succeeded but user could not be resolved', ['gateway' => $gateway, 'tx_ref' => $transaction->tx_ref]);

            return;
        }

        $this->paymentMethods->record($transaction->user, $gateway, $result);

        $this->apiWallet->topUp(
            $transaction->user,
            $transaction->amount_cents,
            'api_wallet_topup_purchase',
            $transaction
        );
    }

    /**
     * "Users should be able to buy more credit tokens if their monthly
     * allocation finishes" — the credit_package_id column IS the purchase
     * record (see its migration), so there's nothing to look up in $result
     * beyond recording the payment method; the credit amount comes straight
     * off the package the checkout controller already attached.
     *
     * @param  array<string, mixed>  $result
     */
    protected function grantCreditTopup(PaymentTransaction $transaction, string $gateway, array $result): void
    {
        $package = $transaction->creditPackage;

        if (! $package || ! $transaction->user_id) {
            Log::error('Credit top-up payment succeeded but package or user could not be resolved', ['gateway' => $gateway, 'tx_ref' => $transaction->tx_ref]);

            return;
        }

        $this->paymentMethods->record($transaction->user, $gateway, $result);

        $this->credits->grant(
            $transaction->user,
            $package->credits,
            'credit_topup_purchase',
            $transaction
        );
    }

    /**
     * A "signup" transaction has no user yet — the account is the reward for
     * paying, not a precondition for it. Create the User from the pending
     * signup record now that payment has verified, then treat the rest of
     * the flow exactly like a normal subscription activation.
     */
    protected function completeSignup(PaymentTransaction $transaction): PaymentTransaction
    {
        $pending = $transaction->pendingSignup;

        if (! $pending) {
            Log::error('Signup payment succeeded but pending signup record is missing', ['tx_ref' => $transaction->tx_ref]);

            return $transaction;
        }

        if ($pending->status === 'completed') {
            // Webhook and browser callback both arrived after success — the
            // user already exists, just resolve it and move on.
            $user = User::where('email', $pending->email)->first();
        } else {
            try {
                $user = User::create([
                    'name' => $pending->name,
                    'email' => $pending->email,
                    'google_id' => $pending->google_id,
                    'password' => $pending->password, // already hashed at signup time
                    // Audit item #8 — carried over from the signup form's
                    // required checkbox, not re-derived here.
                    'refund_policy_accepted_at' => $pending->refund_policy_accepted_at,
                ]);
                $user->assignRole('user');
                $pending->update(['status' => 'completed']);

                // Triggered automatically on signup, per Sam's (the Support
                // Agent's) spec — only here, never in the race-condition
                // "adopt the already-created user" branch below.
                $this->sam->onboardNewUser($user);
            } catch (QueryException $e) {
                // Webhook and callback raced each other into User::create()
                // at the same instant — the other one won, so just adopt it.
                $user = User::where('email', $pending->email)->first();
                $pending->update(['status' => 'completed']);

                if (! $user) {
                    throw $e;
                }
            }
        }

        if ($user) {
            $transaction->update(['type' => 'subscription', 'user_id' => $user->id]);
            $this->referrals->createReferralForNewUser($pending, $user);
        }

        return $transaction->fresh();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function activateSubscription(PaymentTransaction $transaction, string $gateway, array $result): void
    {
        $meta = $result['meta'] ?? [];
        $planId = $meta['plan_id'] ?? null;
        $billingCycle = $meta['billing_cycle'] ?? 'monthly';
        $plan = $planId ? Plan::find($planId) : null;

        if (! $plan || ! $transaction->user_id) {
            Log::error('Payment succeeded but plan or user could not be resolved', ['gateway' => $gateway, 'tx_ref' => $transaction->tx_ref]);

            return;
        }

        $periodEnd = $billingCycle === 'yearly' ? Carbon::now()->addYear() : Carbon::now()->addMonth();

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $transaction->user_id, 'status' => 'active'],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'gateway' => $gateway,
                'gateway_customer_id' => $result['customer_reference'] ?? $transaction->user->email,
                'gateway_subscription_id' => $result['subscription_reference'] ?? null,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
            ]
        );

        $transaction->update(['subscription_id' => $subscription->id]);

        $this->paymentMethods->record($transaction->user, $gateway, $result);

        $this->credits->grant(
            $transaction->user,
            $plan->credits_per_month,
            'monthly_grant',
            $subscription
        );

        $this->referrals->recordCommission($subscription, $transaction);
    }
}
