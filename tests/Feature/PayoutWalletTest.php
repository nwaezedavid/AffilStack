<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Referrals\PayoutDisbursementService;
use App\Services\Referrals\PayoutWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The affiliate payout wallet (audit item #5) — an admin-funded ledger that
 * PayoutDisbursementService draws from to send a ReferralPayout
 * automatically via Flutterwave (Africa/NGN) or PayPal (everyone else),
 * instead of an admin having to log into either gateway's own dashboard.
 * See PayoutWalletService for the ledger itself.
 */
class PayoutWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true, 'credentials' => ['secret_key' => 'flw_test_fake']]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true, 'credentials' => ['client_id' => 'client_fake', 'client_secret' => 'secret_fake']]);
    }

    public function test_topping_up_increases_the_wallet_balance(): void
    {
        $admin = User::factory()->create();
        $wallet = app(PayoutWalletService::class);

        $wallet->topUp('USD', 50000, $admin, 'DEP-1', 'Initial funding');
        $wallet->topUp('USD', 10000, $admin);

        $this->assertSame(60000, $wallet->balance('USD'));
        $this->assertSame(['USD' => 60000], $wallet->balances());
        $this->assertSame('DEP-1', WalletTransaction::where('type', 'top_up')->first()->reference);
    }

    public function test_topping_up_rejects_a_non_positive_amount(): void
    {
        $admin = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PayoutWalletService::class)->topUp('USD', 0, $admin);
    }

    public function test_a_paypal_payout_can_be_auto_disbursed_and_debits_the_wallet(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create([
            'amount_cents' => 8000, 'currency' => 'USD', 'status' => 'requested',
            'payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        app(PayoutWalletService::class)->topUp('USD', 20000, $admin);

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'A21token', 'expires_in' => 32400], 200),
            '*/v1/payments/payouts' => Http::response(['batch_header' => ['payout_batch_id' => 'PAYOUTBATCH1', 'batch_status' => 'PENDING']], 201),
        ]);

        app(PayoutDisbursementService::class)->disburse($payout, $admin);

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('PAYOUTBATCH1', $payout->reference);
        $this->assertSame(12000, app(PayoutWalletService::class)->balance('USD'));

        $ledgerRow = WalletTransaction::where('referral_payout_id', $payout->id)->sole();
        $this->assertSame(-8000, $ledgerRow->amount_cents);
        $this->assertSame('paypal', $ledgerRow->gateway);
    }

    public function test_a_bank_transfer_payout_can_be_auto_disbursed_when_a_bank_code_is_on_file(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create([
            'amount_cents' => 500000, 'currency' => 'NGN', 'status' => 'requested',
            'payout_method' => 'bank_transfer',
            'payout_details' => ['account_name' => 'Jane Affiliate', 'account_number' => '0123456789', 'bank_name' => 'GTBank', 'bank_code' => '058'],
        ]);
        app(PayoutWalletService::class)->topUp('NGN', 1000000, $admin);

        Http::fake(['api.flutterwave.com/v3/transfers' => Http::response(['status' => 'success', 'data' => ['id' => 998877]], 200)]);

        app(PayoutDisbursementService::class)->disburse($payout, $admin);

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('998877', $payout->reference);
        $this->assertSame(500000, app(PayoutWalletService::class)->balance('NGN'));

        Http::assertSent(fn ($request) => $request['account_bank'] === '058' && $request['account_number'] === '0123456789');
    }

    public function test_a_bank_transfer_payout_without_a_bank_code_cannot_be_auto_disbursed(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create([
            'amount_cents' => 500000, 'currency' => 'NGN', 'status' => 'requested',
            'payout_method' => 'bank_transfer',
            'payout_details' => ['account_name' => 'Jane Affiliate', 'account_number' => '0123456789', 'bank_name' => 'GTBank'],
        ]);
        app(PayoutWalletService::class)->topUp('NGN', 1000000, $admin);

        $this->assertFalse(app(PayoutDisbursementService::class)->canAutoDisburse($payout));

        $this->expectException(InvalidArgumentException::class);

        app(PayoutDisbursementService::class)->disburse($payout, $admin);
    }

    public function test_disbursement_is_blocked_when_the_wallet_balance_is_insufficient(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create([
            'amount_cents' => 8000, 'currency' => 'USD', 'status' => 'requested',
            'payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        app(PayoutWalletService::class)->topUp('USD', 1000, $admin);

        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(PayoutDisbursementService::class)->disburse($payout, $admin);
        } finally {
            Http::assertNothingSent();
            $this->assertSame('requested', $payout->fresh()->status);
        }
    }

    public function test_a_payout_that_is_not_requested_cannot_be_disbursed_again(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create(['status' => 'paid']);

        $this->expectException(InvalidArgumentException::class);

        app(PayoutDisbursementService::class)->disburse($payout, $admin);
    }
}
