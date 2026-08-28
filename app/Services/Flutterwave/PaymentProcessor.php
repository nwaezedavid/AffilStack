<?php

namespace App\Services\Flutterwave;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Services\Referrals\ReferralService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * The one place that turns a verified Flutterwave transaction into an
 * active subscription + credit grant. Called from both the browser redirect
 * callback and the server-to-server webhook, so it must be idempotent —
 * either caller might arrive first, or the webhook might retry.
 */
class PaymentProcessor
{
    public function __construct(protected CreditManager $credits, protected ReferralService $referrals) {}

    public function process(array $flwData): ?PaymentTransaction
    {
        $txRef = (string) ($flwData['tx_ref'] ?? '');
        $transaction = PaymentTransaction::where('tx_ref', $txRef)->first();

        if (! $transaction) {
            Log::warning('Flutterwave payment for unknown tx_ref', ['tx_ref' => $txRef]);

            return null;
        }

        // Already processed by the other caller (webhook vs. redirect race) — no-op.
        if ($transaction->status === 'successful') {
            return $transaction;
        }

        $status = (string) ($flwData['status'] ?? '');
        $expectedAmount = $transaction->amount_cents / 100;
        $actualAmount = (float) ($flwData['amount'] ?? 0);
        $actualCurrency = (string) ($flwData['currency'] ?? '');

        if ($status !== 'successful' || $actualAmount < $expectedAmount || $actualCurrency !== $transaction->currency) {
            $transaction->update([
                'status' => 'failed',
                'flutterwave_tx_id' => (string) ($flwData['id'] ?? $transaction->flutterwave_tx_id),
                'raw_payload' => $flwData,
                'processed_at' => now(),
            ]);

            Log::warning('Flutterwave payment failed verification', [
                'tx_ref' => $txRef, 'status' => $status,
                'expected' => $expectedAmount, 'actual' => $actualAmount,
            ]);

            return $transaction;
        }

        $transaction->update([
            'status' => 'successful',
            'flutterwave_tx_id' => (string) ($flwData['id'] ?? ''),
            'raw_payload' => $flwData,
            'processed_at' => now(),
        ]);

        if ($transaction->type === 'signup') {
            $transaction = $this->completeSignup($transaction);
        }

        if ($transaction->type === 'subscription') {
            $this->activateSubscription($transaction, $flwData);
        }

        return $transaction;
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
            Log::error('Flutterwave signup payment succeeded but pending signup record is missing', ['tx_ref' => $transaction->tx_ref]);

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
                    'password' => $pending->password, // already hashed at signup time
                ]);
                $user->assignRole('user');
                $pending->update(['status' => 'completed']);
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

    protected function activateSubscription(PaymentTransaction $transaction, array $flwData): void
    {
        $meta = $flwData['meta'] ?? [];
        $planId = $meta['plan_id'] ?? null;
        $billingCycle = $meta['billing_cycle'] ?? 'monthly';
        $plan = $planId ? Plan::find($planId) : null;

        if (! $plan || ! $transaction->user_id) {
            Log::error('Flutterwave payment succeeded but plan or user could not be resolved', ['tx_ref' => $transaction->tx_ref]);

            return;
        }

        $periodEnd = $billingCycle === 'yearly' ? Carbon::now()->addYear() : Carbon::now()->addMonth();

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $transaction->user_id, 'status' => 'active'],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'flutterwave_customer_email' => $transaction->user->email,
                'flutterwave_tx_ref' => $transaction->tx_ref,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
            ]
        );

        $transaction->update(['subscription_id' => $subscription->id]);

        $this->credits->grant(
            $transaction->user,
            $plan->credits_per_month,
            'monthly_grant',
            $subscription
        );

        $this->referrals->recordCommission($subscription, $transaction);
    }
}
