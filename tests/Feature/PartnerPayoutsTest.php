<?php

namespace Tests\Feature;

use App\Filament\Resources\ReferralPayouts\Pages\ListReferralPayouts;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReferralPayout;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ReferralPayoutProcessed;
use App\Support\ReferralProgram;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerPayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_example', 'services.paystack.usd_to_ngn_rate' => 1500]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function bankPayout(array $attributes = []): ReferralPayout
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $payout = ReferralPayout::factory()->create(array_merge([
            'user_id' => $partner->id,
            'amount_cents' => 10000,
            'currency' => 'USD',
            'payout_method' => 'paystack_bank',
            'payout_details' => ['bank_code' => '044', 'bank_name' => 'Access Bank', 'account_number' => '0123456789', 'account_name' => 'ADA OBI', 'verified' => true],
        ], $attributes));
        ReferralEvent::factory()->approved()->create([
            'referral_id' => Referral::factory()->create(['referrer_id' => $partner->id])->id,
            'referral_payout_id' => $payout->id,
            'amount_cents' => $payout->amount_cents,
            'currency' => $payout->currency,
        ]);

        return $payout;
    }

    public function test_a_partner_saves_bank_details_checked_with_paystack_and_requests_a_payout(): void
    {
        Http::fake([
            '*/bank?*' => Http::response(['status' => true, 'data' => [['name' => 'Access Bank', 'code' => '044', 'active' => true]], 'meta' => ['next' => null]]),
            '*/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_name' => 'ADA OBI']]),
        ]);
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        ReferralEvent::factory()->approved()->create(['referral_id' => Referral::factory()->create(['referrer_id' => $partner->id])->id, 'amount_cents' => 6000]);

        $this->actingAs($partner)->postJson(route('partner.payouts.resolve'), ['bank_code' => '044', 'account_number' => '0123456789'])
            ->assertOk()->assertJson(['account_name' => 'ADA OBI']);

        $this->post(route('partner.payouts.method'), ['payout_method' => 'paystack_bank', 'bank_code' => '044', 'account_number' => '0123456789'])
            ->assertRedirect(route('partner.payouts'));
        $this->get(route('partner.payouts'))->assertSee('Access Bank · ****6789 · ADA OBI');

        $this->post(route('partner.payouts.request'), ['currency' => 'USD'])->assertSessionHas('success');
        $payout = $partner->referralPayouts()->sole();
        $this->assertSame('paystack_bank', $payout->payout_method);
        $this->assertSame(6000, $payout->amount_cents);
    }

    public function test_an_unknown_account_number_is_refused(): void
    {
        Http::fake([
            '*/bank?*' => Http::response(['status' => true, 'data' => [['name' => 'Access Bank', 'code' => '044']], 'meta' => []]),
            '*/bank/resolve*' => Http::response(['status' => false, 'message' => 'Could not resolve account name'], 422),
        ]);
        $partner = User::factory()->create(['is_affiliate_only' => true]);

        $this->actingAs($partner)->post(route('partner.payouts.method'), ['payout_method' => 'paystack_bank', 'bank_code' => '044', 'account_number' => '0000000000'])
            ->assertSessionHasErrors('account_number');
        $this->assertNull($partner->fresh()->payout_method);
    }

    public function test_naira_balances_use_the_converted_minimum(): void
    {
        SiteSetting::set(ReferralProgram::MINIMUM_PAYOUT_SETTING, '50');

        $this->assertSame(5000, ReferralProgram::minimumPayoutCents('USD'));
        $this->assertSame(7500000, ReferralProgram::minimumPayoutCents('NGN'));
    }

    public function test_paying_a_bank_payout_with_paystack_queues_a_transfer_and_the_webhook_completes_it(): void
    {
        Notification::fake();
        Http::fake([
            '*/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_123']]),
            '*/transfer' => Http::response(['status' => true, 'message' => 'Transfer has been queued', 'data' => ['status' => 'pending', 'reference' => 'ignored', 'transfer_code' => 'TRF_1']]),
        ]);
        $payout = $this->bankPayout();

        Livewire::actingAs($this->admin)->test(ListReferralPayouts::class)->callTableAction('disburseFromWallet', $payout);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer') && $request['amount'] === 15000000 && $request['recipient'] === 'RCP_123');
        $payout->refresh();
        $this->assertSame('disbursing', $payout->status);
        $this->assertSame(15000000, (int) $payout->sent_amount_cents);
        $this->assertSame('NGN', $payout->sent_currency);
        Notification::assertNothingSent();

        $body = json_encode(['event' => 'transfer.success', 'data' => ['reference' => $payout->reference, 'status' => 'success']]);
        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_example'),
        ], $body)->assertOk();

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('paid', $payout->events()->sole()->status);
        $this->assertSame($this->admin->id, $payout->processed_by_id);
        Notification::assertSentTo($payout->user, ReferralPayoutProcessed::class);
    }

    public function test_a_failed_paystack_transfer_returns_the_payout_to_the_queue(): void
    {
        Http::fake([
            '*/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_123']]),
            '*/transfer' => Http::response(['status' => true, 'data' => ['status' => 'pending']]),
        ]);
        $payout = $this->bankPayout(['currency' => 'NGN', 'amount_cents' => 9000000]);

        Livewire::actingAs($this->admin)->test(ListReferralPayouts::class)->callTableAction('disburseFromWallet', $payout);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer') && $request['amount'] === 9000000);

        $body = json_encode(['event' => 'transfer.failed', 'data' => ['reference' => $payout->fresh()->reference, 'reason' => 'Account closed']]);
        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_example'),
        ], $body)->assertOk();

        $payout->refresh();
        $this->assertSame('requested', $payout->status);
        $this->assertStringContainsString('Account closed', $payout->note);
    }

    public function test_a_refused_transfer_leaves_the_payout_requested(): void
    {
        Http::fake([
            '*/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_123']]),
            '*/transfer' => Http::response(['status' => false, 'message' => 'Your balance is not enough to fulfil this request'], 400),
        ]);
        $payout = $this->bankPayout();

        Livewire::actingAs($this->admin)->test(ListReferralPayouts::class)->callTableAction('disburseFromWallet', $payout)->assertNotified();

        $this->assertSame('requested', $payout->fresh()->status);
    }

    public function test_usdt_payouts_need_a_real_transaction_hash(): void
    {
        Notification::fake();
        $payout = $this->bankPayout(['payout_method' => 'usdt', 'payout_details' => ['network' => 'trc20', 'address' => 'T'.str_repeat('A', 33)]]);

        Livewire::actingAs($this->admin)->test(ListReferralPayouts::class)
            ->callTableAction('markPaid', $payout, data: ['reference' => 'sent it'])
            ->assertHasTableActionErrors(['reference']);
        $this->assertSame('requested', $payout->fresh()->status);

        $hash = str_repeat('ab', 32);
        Livewire::actingAs($this->admin)->test(ListReferralPayouts::class)
            ->callTableAction('markPaid', $payout, data: ['reference' => $hash])
            ->assertHasNoTableActionErrors();

        $this->assertSame('paid', $payout->fresh()->status);
        $this->actingAs($payout->user)->get(route('partner.payouts'))->assertSee('tronscan.org/#/transaction/'.$hash, false);
    }
}
