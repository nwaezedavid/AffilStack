<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Services\Referrals\ReferralService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Audit gap #6: neither gateway webhook handled a refund or chargeback at
 * all, so a referrer kept commission on money that was later given back.
 * Covers both gateways' webhook detection (RefundProcessor's lookup) and
 * ReferralService::reverseCommission()'s two clawback paths — voiding a
 * commission that hadn't been claimed yet, vs. offsetting one that was
 * already paid (or already claimed by a pending payout) with a negative
 * ledger entry rather than rewriting history.
 */
class RefundClawbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        config(['services.stripe.secret_key' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_test_fake']);
        config(['services.flutterwave.secret_hash' => 'flw_test_hash']);
    }

    protected function postStripeWebhook(array $event): TestResponse
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_fake');

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function postFlutterwaveWebhook(array $payload): TestResponse
    {
        return $this->postJson('/webhooks/flutterwave', $payload, ['verif-hash' => 'flw_test_hash']);
    }

    /**
     * @param  array<string, mixed>  $transactionAttrs
     * @return array{referrer: User, referral: Referral, transaction: PaymentTransaction, event: ReferralEvent}
     */
    protected function makeCommissionedPayment(string $gateway, array $transactionAttrs, string $eventStatus = 'approved'): array
    {
        $referrer = User::factory()->create();
        $referredUser = User::factory()->create();
        $referral = Referral::factory()->create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'status' => 'converted',
        ]);

        $transaction = PaymentTransaction::create(array_merge([
            'user_id' => $referredUser->id,
            'type' => 'subscription',
            'gateway' => $gateway,
            'tx_ref' => 'ref_'.Str::uuid(),
            'amount_cents' => 6700,
            'currency' => 'USD',
            'status' => 'successful',
            'processed_at' => now(),
        ], $transactionAttrs));

        $event = ReferralEvent::create([
            'referral_id' => $referral->id,
            'payment_transaction_id' => $transaction->id,
            'event_type' => 'first_payment',
            'amount_cents' => 1340,
            'currency' => 'USD',
            'status' => $eventStatus,
            'occurred_at' => now(),
        ]);

        return compact('referrer', 'referral', 'transaction', 'event');
    }

    public function test_a_stripe_refund_marks_the_transaction_refunded_and_reverses_an_unclaimed_commission(): void
    {
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'pi_test_refund_1',
        ]);

        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_test_refund_1',
                'amount_refunded' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);

        $event->refresh();
        $this->assertSame('reversed', $event->status);
        $this->assertSame(0, ReferralEvent::where('event_type', 'refund')->count());
    }

    public function test_a_stripe_chargeback_marks_the_transaction_charged_back_and_reverses_an_unclaimed_commission(): void
    {
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'pi_test_dispute_1',
        ]);

        $this->postStripeWebhook([
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'payment_intent' => 'pi_test_dispute_1',
                'amount' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('charged_back', $transaction->status);

        $event->refresh();
        $this->assertSame('reversed', $event->status);
    }

    public function test_a_stripe_refund_on_an_already_paid_commission_leaves_history_intact_and_adds_a_clawback_entry(): void
    {
        ['referrer' => $referrer, 'transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'pi_test_refund_2',
        ], eventStatus: 'paid');

        $payout = ReferralPayout::factory()->create(['user_id' => $referrer->id, 'status' => 'paid', 'amount_cents' => 1340, 'currency' => 'USD']);
        $event->update(['status' => 'paid', 'referral_payout_id' => $payout->id]);

        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_test_refund_2',
                'amount_refunded' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);

        // The original, already-paid event keeps its history — "paid"
        // must keep meaning a real payout batch went out.
        $event->refresh();
        $this->assertSame('paid', $event->status);
        $this->assertSame($payout->id, $event->referral_payout_id);

        $clawback = ReferralEvent::where('payment_transaction_id', $transaction->id)->where('event_type', 'refund')->sole();
        $this->assertSame(-1340, $clawback->amount_cents);
        $this->assertSame('approved', $clawback->status);
        $this->assertNull($clawback->referral_payout_id);
        $this->assertSame($referrer->id, $clawback->referral->referrer_id);
    }

    public function test_a_stripe_refund_matches_a_renewal_transaction_by_invoice_id(): void
    {
        // A renewal's PaymentTransaction is stored under its invoice id, not
        // a payment_intent — see SubscriptionRenewalService::renew().
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'in_test_renewal_1',
            'type' => 'renewal',
        ]);

        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_unrelated_to_stored_id',
                'invoice' => 'in_test_renewal_1',
                'amount_refunded' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);
        $event->refresh();
        $this->assertSame('reversed', $event->status);
    }

    public function test_a_flutterwave_refund_marks_the_transaction_refunded_and_reverses_the_commission(): void
    {
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('flutterwave', [
            'gateway_tx_id' => '998877',
        ]);

        // Refund webhooks are a bare object with no event/data envelope —
        // see FlutterwaveGateway::resolveRefundEvent().
        $this->postFlutterwaveWebhook([
            'id' => 99025,
            'AmountRefunded' => 67,
            'status' => 'completed',
            'FlwRef' => '4687213286',
            'TransactionId' => 998877,
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);
        $event->refresh();
        $this->assertSame('reversed', $event->status);
    }

    public function test_a_flutterwave_chargeback_is_matched_by_flw_ref_not_transaction_id(): void
    {
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('flutterwave', [
            'gateway_tx_id' => '998877',
            'gateway_reference' => 'FLW-REF-abc123',
        ]);

        // A chargeback identifies the disputed charge by flw_ref, which is
        // NOT the same value as the numeric transaction id it's normally
        // looked up by — this must still resolve to the same transaction.
        Http::fake(['api.flutterwave.com/v3/transactions/*' => Http::response(['status' => 'success', 'data' => ['id' => 1]], 200)]);

        $this->postFlutterwaveWebhook([
            'event' => 'chargeback.initiated',
            'data' => [
                'id' => 22221,
                'flw_ref' => 'FLW-REF-abc123',
                'amount' => 67,
                'status' => 'initiated',
            ],
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame('charged_back', $transaction->status);
        $event->refresh();
        $this->assertSame('reversed', $event->status);

        // Regression guard: a chargeback's data.id (22221) must never be
        // sent to /transactions/{id}/verify as if it were a charge id —
        // that endpoint was never called at all for this webhook.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/transactions/'));
    }

    public function test_replaying_the_same_refund_webhook_is_idempotent(): void
    {
        ['transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'pi_test_replay_1',
        ]);

        $payload = [
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_test_replay_1',
                'amount_refunded' => 6700,
                'currency' => 'usd',
            ]],
        ];

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);
        $event->refresh();
        $this->assertSame('reversed', $event->status);
    }

    public function test_a_refund_for_an_unknown_transaction_is_a_safe_no_op(): void
    {
        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_does_not_exist',
                'amount_refunded' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertOk();

        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_a_clawback_nets_against_the_referrers_other_approved_commissions_in_the_same_currency(): void
    {
        ['referrer' => $referrer, 'transaction' => $transaction, 'event' => $event] = $this->makeCommissionedPayment('stripe', [
            'gateway_tx_id' => 'pi_test_netting_1',
        ], eventStatus: 'paid');

        $payout = ReferralPayout::factory()->create(['user_id' => $referrer->id, 'status' => 'paid', 'amount_cents' => 1340, 'currency' => 'USD']);
        $event->update(['status' => 'paid', 'referral_payout_id' => $payout->id]);

        // An unrelated, still-unclaimed approved commission in the same
        // currency — the clawback should reduce this balance, not the
        // affiliate's already-paid history.
        $otherReferral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $otherReferral->id, 'currency' => 'USD', 'amount_cents' => 5000]);

        app(ReferralService::class)->reverseCommission($transaction, 'refunded');

        $this->assertSame(3660, $referrer->unpaidApprovedCommissionByCurrency()['USD']);
    }
}
