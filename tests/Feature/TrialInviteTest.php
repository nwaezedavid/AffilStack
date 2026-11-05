<?php

namespace Tests\Feature;

use App\Filament\Resources\TrialInvites\Pages\CreateTrialInvite;
use App\Filament\Resources\TrialInvites\Pages\ListTrialInvites;
use App\Models\CustomerWalletTransaction;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TrialInvite;
use App\Models\User;
use App\Notifications\TrialEndingReminder;
use App\Notifications\TrialInviteNotification;
use App\Notifications\TrialPaymentFailed;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SubscriptionRenewalService;
use App\Services\Trials\TrialInviteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-only 7-day free trial invites: never offered publicly, started
 * only from the signed single-use link, card saved first, charged on day 8.
 */
class TrialInviteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'super-admin']);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        config([
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paystack.usd_to_ngn_rate' => 1600,
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
        ]);

        $this->plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function sendInvite(string $email = 'invitee@example.com'): TrialInvite
    {
        return app(TrialInviteService::class)->send('Ada Invitee', $email, $this->plan, 'monthly', $this->admin);
    }

    /**
     * Opens the signed link, registers, and completes a Paystack card check
     * with a reusable card — the full happy path up to a running trial.
     */
    protected function acceptInvite(TrialInvite $invite): User
    {
        $this->get(app(TrialInviteService::class)->signedUrl($invite))->assertSuccessful();

        $this->post(route('trial-invites.register', $invite), [
            'name' => 'Ada Invitee',
            'password' => 'a-Strong-password-123',
            'password_confirmation' => 'a-Strong-password-123',
            'accepts_refund_policy' => '1',
        ])->assertRedirect();

        $user = User::where('email', $invite->email)->firstOrFail();

        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/trial'],
        ], 200)]);

        $this->withSession(['checkout_country' => 'NG'])
            ->post(route('trial-invites.start', $invite), ['gateway' => 'paystack'])
            ->assertRedirect('https://checkout.paystack.com/trial');

        $transaction = PaymentTransaction::where('user_id', $user->id)->where('type', 'trial_card_check')->sole();

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'id' => 9, 'reference' => $transaction->tx_ref, 'status' => 'success',
            'amount' => $transaction->amount_cents, 'currency' => 'NGN', 'customer' => ['email' => $user->email],
            'metadata' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'type' => 'trial_card_check'],
            'authorization' => ['reusable' => true, 'authorization_code' => 'AUTH_trial', 'card_type' => 'visa', 'last4' => '1111', 'exp_month' => '09', 'exp_year' => '2030'],
        ]], 200)]);

        $this->withSession(['checkout_country' => 'NG'])
            ->get(route('billing.callback', ['gateway' => 'paystack', 'reference' => $transaction->tx_ref]))
            ->assertRedirect(route('billing.index'));

        // A fresh model, as the next real request would load.
        $this->actingAs($user = $user->fresh());

        return $user;
    }

    public function test_admin_sends_an_invite_with_a_signed_link(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin)
            ->test(CreateTrialInvite::class)
            ->fillForm(['name' => 'Ada Invitee', 'email' => 'Invitee@Example.com', 'plan_id' => $this->plan->id, 'billing_cycle' => 'yearly'])
            ->call('create')
            ->assertHasNoFormErrors();

        $invite = TrialInvite::sole();
        $this->assertSame('invitee@example.com', $invite->email);
        $this->assertSame(TrialInvite::STATUS_SENT, $invite->status);
        $this->assertTrue($invite->expires_at->isSameDay(now()->addDays(7)));

        Notification::assertSentTo(new AnonymousNotifiable, TrialInviteNotification::class, function (TrialInviteNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
            return array_key_exists('invitee@example.com', $notifiable->routes['mail'])
                && str_contains($notification->url, 'signature=');
        });

        Livewire::actingAs($this->admin)->test(ListTrialInvites::class)->assertCanSeeTableRecords([$invite])->assertSee('Sent');
    }

    public function test_one_trial_per_email_address(): void
    {
        Notification::fake();
        $this->sendInvite();

        Livewire::actingAs($this->admin)
            ->test(CreateTrialInvite::class)
            ->fillForm(['name' => 'Again', 'email' => 'invitee@example.com', 'plan_id' => $this->plan->id, 'billing_cycle' => 'monthly'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, TrialInvite::count());
    }

    public function test_the_trial_is_never_offered_publicly(): void
    {
        $invite = $this->sendInviteQuietly();

        $this->get(route('trial-invites.show', $invite))->assertForbidden();
        $this->get(route('registration.pricing'))->assertSuccessful()->assertDontSee('free trial', false)->assertDontSee('/trial/', false);
        $this->get(route('registration.form', $this->plan))->assertDontSee('free trial', false);
    }

    public function test_accepting_the_invite_saves_a_card_and_starts_a_seven_day_trial(): void
    {
        $invite = $this->sendInviteQuietly();

        $user = $this->acceptInvite($invite);

        $subscription = $user->activeSubscription;
        $this->assertSame('trialing', $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->isSameDay(now()->addDays(7)));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('AUTH_trial', PaymentMethod::where('user_id', $user->id)->sole()->gateway_token);
        $this->assertSame($subscription->payment_method_id, PaymentMethod::where('user_id', $user->id)->sole()->id);
        $this->assertSame(TrialInvite::STATUS_ACCEPTED, $invite->fresh()->status);
        $this->assertSame(100, app(CustomerWalletManager::class)->balance($user), 'The card check is kept as wallet balance.');

        $this->get(route('billing.index'))
            ->assertSee('Free trial ends on '.$subscription->trial_ends_at->format('M j, Y').', then $67.00/month')
            ->assertSee('Cancel trial');
    }

    public function test_the_link_works_only_once(): void
    {
        $invite = $this->sendInviteQuietly();
        $this->acceptInvite($invite);

        $this->get(app(TrialInviteService::class)->signedUrl($invite->fresh()))
            ->assertSuccessful()
            ->assertSee('already been used or has expired');

        $this->post(route('trial-invites.start', $invite), ['gateway' => 'paystack'])->assertStatus(410);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $invite = $this->sendInviteQuietly();
        $url = app(TrialInviteService::class)->signedUrl($invite);

        $this->travel(8)->days();

        $this->get($url)->assertForbidden();
        $this->assertSame(1, app(TrialInviteService::class)->expireUnusedInvites());
        $this->assertSame(TrialInvite::STATUS_EXPIRED, $invite->fresh()->status);
    }

    public function test_the_invite_only_works_for_the_invited_email(): void
    {
        $invite = $this->sendInviteQuietly();
        $someoneElse = User::factory()->create();
        $someoneElse->assignRole('user');

        $this->actingAs($someoneElse)
            ->get(app(TrialInviteService::class)->signedUrl($invite))
            ->assertSee('This invite was sent to invitee@example.com');

        $this->post(route('trial-invites.start', $invite), ['gateway' => 'paystack'])->assertSessionHas('error');
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_paypal_is_not_offered_because_it_cannot_save_a_card(): void
    {
        $this->assertSame(['paystack'], array_map(
            fn ($gateway) => $gateway->key(),
            app(TrialInviteService::class)->cardGateways(app(PaymentGatewayManager::class)->enabled()),
        ));
    }

    public function test_cancelling_the_trial_stops_any_charge_and_access_ends_with_the_trial(): void
    {
        $invite = $this->sendInviteQuietly();
        $user = $this->acceptInvite($invite);

        $this->post(route('billing.cancel-trial'))->assertSessionHas('success');

        $subscription = Subscription::where('user_id', $user->id)->sole();
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertSame(TrialInvite::STATUS_CANCELLED, $invite->fresh()->status);

        Http::fake();
        $this->travel(8)->days();

        $this->assertSame(['converted' => 0, 'failed' => 0], app(TrialInviteService::class)->convertDue());
        Http::assertNothingSent();
        app(SubscriptionRenewalService::class)->expireLapsed();
        $this->assertSame('expired', $subscription->fresh()->status);
    }

    public function test_day_eight_charges_the_wallet_first_then_the_saved_card(): void
    {
        $invite = $this->sendInviteQuietly();
        $user = $this->acceptInvite($invite);

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'ref_conv']], 200)]);
        $this->travel(7)->days();
        $this->travel(1)->hours();

        $this->assertSame(['converted' => 1, 'failed' => 0], app(TrialInviteService::class)->convertDue());

        $subscription = Subscription::where('user_id', $user->id)->sole();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->current_period_end->isSameDay(now()->addMonth()));
        $this->assertSame(TrialInvite::STATUS_CONVERTED, $invite->fresh()->status);
        $this->assertSame(0, app(CustomerWalletManager::class)->balance($user));
        $this->assertSame(-100, CustomerWalletTransaction::where('user_id', $user->id)->where('type', 'trial_conversion')->sole()->amount_minor);
        // $67.00 less the $1.00 card check already in the wallet.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'charge_authorization') && $request['amount'] === (int) round(66.00 * 1600 * 100));
        $this->assertSame(6700, PaymentTransaction::where('user_id', $user->id)->where('type', 'trial_conversion')->sole()->amount_cents);
    }

    public function test_a_failed_day_eight_charge_marks_the_subscription_past_due_and_emails_the_customer(): void
    {
        $invite = $this->sendInviteQuietly();
        $user = $this->acceptInvite($invite);
        Notification::fake();

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'failed', 'gateway_response' => 'Insufficient funds']], 200)]);
        $this->travel(8)->days();

        $this->assertSame(['converted' => 0, 'failed' => 1], app(TrialInviteService::class)->convertDue());

        $this->assertSame('past_due', Subscription::where('user_id', $user->id)->sole()->status);
        $this->assertSame(100, app(CustomerWalletManager::class)->balance($user), 'The wallet is untouched when the card fails.');
        Notification::assertSentTo($user, TrialPaymentFailed::class);
    }

    public function test_a_reminder_goes_out_two_days_before_the_trial_ends(): void
    {
        $invite = $this->sendInviteQuietly();
        $user = $this->acceptInvite($invite);
        Notification::fake();

        $this->assertSame(0, app(TrialInviteService::class)->sendReminders());

        $this->travel(5)->days();
        $this->travel(1)->hours();
        $this->assertSame(1, app(TrialInviteService::class)->sendReminders());
        $this->assertSame(0, app(TrialInviteService::class)->sendReminders(), 'Sent once.');

        Notification::assertSentTo($user, TrialEndingReminder::class);
    }

    public function test_paying_for_a_plan_during_the_trial_replaces_the_trial(): void
    {
        $invite = $this->sendInviteQuietly();
        $user = $this->acceptInvite($invite);
        app(CustomerWalletManager::class)->credit($user, 6600, 'admin_credit', 'Test funding');

        $this->post(route('billing.checkout', $this->plan), ['billing_cycle' => 'monthly', 'pay_from_wallet' => 1])
            ->assertSessionHas('success');

        $this->assertSame('canceled', Subscription::where('user_id', $user->id)->where('gateway', 'paystack')->sole()->status);
        $this->assertSame('active', $user->fresh()->activeSubscription->status);
        $this->assertSame(TrialInvite::STATUS_CONVERTED, $invite->fresh()->status);

        Http::fake();
        $this->travel(8)->days();
        $this->assertSame(['converted' => 0, 'failed' => 0], app(TrialInviteService::class)->convertDue());
        Http::assertNothingSent();
    }

    protected function sendInviteQuietly(): TrialInvite
    {
        Notification::fake();
        $invite = $this->sendInvite();
        Notification::swap(app(ChannelManager::class));

        return $invite;
    }
}
