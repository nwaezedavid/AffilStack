<?php

namespace Tests\Feature;

use App\Filament\Pages\AffiliateProgramSettings;
use App\Filament\Resources\PartnerAnnouncements\Pages\CreatePartnerAnnouncement;
use App\Filament\Resources\PartnerAnnouncements\Pages\ListPartnerAnnouncements;
use App\Models\AffiliateApplication;
use App\Models\PartnerAnnouncement;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralCampaign;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AffiliateApplicationApproved;
use App\Notifications\PartnerAnnouncementPublished;
use App\Notifications\PartnerResetPassword;
use App\Services\Referrals\AffiliateApplicationService;
use App\Support\ReferralProgram;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    protected function partner(array $attributes = []): User
    {
        $partner = User::factory()->create(['is_affiliate_only' => true, 'password' => Hash::make('partner-pass-123')] + $attributes);
        $partner->assignRole('user');

        return $partner;
    }

    public function test_a_partner_signs_in_on_the_partner_portal(): void
    {
        $partner = $this->partner(['email' => 'ada@example.com']);

        $this->get(route('partner.login'))->assertOk()->assertSee('Partner sign in');

        $this->post(route('partner.login.store'), ['email' => 'ada@example.com', 'password' => 'partner-pass-123'])
            ->assertRedirect(route('partner.dashboard'));

        $this->assertAuthenticatedAs($partner);
        $this->get(route('partner.dashboard'))->assertOk()->assertSee('Welcome back');
    }

    public function test_customers_and_wrong_passwords_are_turned_away_from_the_partner_sign_in(): void
    {
        $customer = User::factory()->create(['email' => 'cust@example.com', 'password' => Hash::make('customer-pass-1')]);
        $this->partner(['email' => 'ada@example.com']);

        $this->post(route('partner.login.store'), ['email' => 'cust@example.com', 'password' => 'customer-pass-1'])
            ->assertSessionHasErrors('email');
        $this->post(route('partner.login.store'), ['email' => 'ada@example.com', 'password' => 'nope-nope-nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->actingAs($customer)->get(route('partner.dashboard'))->assertRedirect(route('referrals.index'));
    }

    public function test_partners_cannot_sign_in_to_the_customer_app(): void
    {
        $this->partner(['email' => 'ada@example.com']);

        $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'partner-pass-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_partner_sign_in(): void
    {
        $this->get(route('partner.payouts'))->assertRedirect(route('partner.login'));
    }

    public function test_suspended_partners_are_signed_out(): void
    {
        $partner = $this->partner(['is_suspended' => true]);

        $this->actingAs($partner)->get(route('partner.dashboard'))->assertRedirect(route('partner.login'));
        $this->assertGuest();
    }

    public function test_two_factor_sign_in_for_partners(): void
    {
        $partner = $this->partner(['email' => 'ada@example.com']);

        $this->actingAs($partner)->post(route('partner.two-factor.enable'), ['password' => 'partner-pass-123'])->assertRedirect();
        $partner->refresh();
        $this->assertNotNull($partner->two_factor_secret);
        $this->assertNull($partner->two_factor_confirmed_at);
        $this->get(route('partner.account'))->assertOk()->assertSee('Confirm and turn on');

        $secret = Fortify::currentEncrypter()->decrypt($partner->two_factor_secret);
        $google = new Google2FA;

        $this->post(route('partner.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('partner.two-factor.confirm'), ['code' => $google->getCurrentOtp($secret)])->assertSessionHasNoErrors();
        $this->assertTrue($partner->fresh()->hasEnabledTwoFactorAuthentication());
        $this->get(route('partner.account'))->assertSee('Recovery codes');

        $this->post(route('partner.logout'));
        $this->assertGuest();

        $this->post(route('partner.login.store'), ['email' => 'ada@example.com', 'password' => 'partner-pass-123'])
            ->assertRedirect(route('partner.two-factor'));
        $this->assertGuest();

        $this->post(route('partner.two-factor.store'), ['code' => '123123'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $recovery = $partner->fresh()->recoveryCodes()[0];
        $this->post(route('partner.two-factor.store'), ['recovery_code' => $recovery])->assertRedirect(route('partner.dashboard'));
        $this->assertAuthenticatedAs($partner);
        $this->assertNotContains($recovery, $partner->fresh()->recoveryCodes());
    }

    public function test_turning_two_factor_off_needs_the_password(): void
    {
        $partner = $this->partner();
        $this->actingAs($partner)->post(route('partner.two-factor.enable'), ['password' => 'partner-pass-123']);

        $this->delete(route('partner.two-factor.disable'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertNotNull($partner->fresh()->two_factor_secret);

        $this->delete(route('partner.two-factor.disable'), ['password' => 'partner-pass-123'])->assertSessionHasNoErrors();
        $this->assertNull($partner->fresh()->two_factor_secret);
    }

    public function test_partner_password_reset_links_point_at_the_partner_portal(): void
    {
        Notification::fake();
        $partner = $this->partner(['email' => 'ada@example.com']);

        $this->post(route('partner.password.email'), ['email' => 'ada@example.com'])->assertSessionHas('success');
        $this->post(route('partner.password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($partner, PartnerResetPassword::class, function (PartnerResetPassword $notification) use ($partner, &$token) {
            $token = $notification->token;

            return str_contains($notification->toMail($partner)->actionUrl, '/affiliate/reset-password/');
        });

        $this->post(route('partner.password.update'), [
            'token' => $token, 'email' => 'ada@example.com', 'password' => 'brand-new-pass-77', 'password_confirmation' => 'brand-new-pass-77',
        ])->assertRedirect(route('partner.login'));

        $this->assertTrue(Hash::check('brand-new-pass-77', $partner->fresh()->password));
    }

    public function test_every_portal_page_renders(): void
    {
        SiteSetting::setFlag(ReferralProgram::LEADERBOARD_SETTING, true);
        $partner = $this->partner();
        ReferralCampaign::factory()->for($partner)->create(['name' => 'YouTube review']);
        PartnerAnnouncement::factory()->create(['title' => 'Double commission weekend']);

        foreach (['partner.dashboard', 'partner.links', 'partner.banners', 'partner.promote', 'partner.guidelines', 'partner.payouts', 'partner.news', 'partner.leaderboard', 'partner.account'] as $route) {
            $this->actingAs($partner)->get(route($route))->assertOk();
        }

        foreach (['7d', '90d', '12m'] as $range) {
            $this->actingAs($partner)->get(route('partner.dashboard', ['range' => $range]))->assertOk();
        }
    }

    public function test_news_shows_an_unread_badge_until_opened(): void
    {
        $partner = $this->partner();
        PartnerAnnouncement::factory()->create(['title' => 'New banners are live']);
        PartnerAnnouncement::factory()->draft()->create(['title' => 'Secret draft']);

        $this->actingAs($partner)->get(route('partner.dashboard'))->assertSee('1<span class="sr-only"> unread</span>', false);
        $this->actingAs($partner)->get(route('partner.news'))->assertSee('New banners are live')->assertDontSee('Secret draft');
        $this->actingAs($partner)->get(route('partner.dashboard'))->assertDontSee('<span class="sr-only"> unread</span>', false);
    }

    public function test_the_leaderboard_is_off_until_the_admin_turns_it_on_and_respects_privacy(): void
    {
        $partner = $this->partner(['name' => 'Ada Obi']);
        $this->actingAs($partner)->get(route('partner.leaderboard'))->assertNotFound();

        SiteSetting::setFlag(ReferralProgram::LEADERBOARD_SETTING, true);
        $shy = $this->partner(['name' => 'Bola Shy', 'show_on_partner_leaderboard' => false]);
        foreach ([[$partner, 2], [$shy, 3]] as [$referrer, $count]) {
            Referral::factory()->count($count)->create(['referrer_id' => $referrer->id, 'status' => 'converted', 'converted_at' => now()]);
        }

        $this->actingAs($partner)->get(route('partner.leaderboard'))
            ->assertOk()
            ->assertSee('Ada O.')
            ->assertDontSee('Bola')
            ->assertSee('#2');
    }

    public function test_campaign_links_carry_attribution_from_click_to_commission_at_the_partner_rate(): void
    {
        Notification::fake();
        SiteSetting::set(ReferralProgram::PARTNER_RATE_SETTING, '30');
        SiteSetting::set(ReferralProgram::CUSTOMER_RATE_SETTING, '10');
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
        $partner = $this->partner();

        $this->actingAs($partner)->post(route('partner.campaigns.store'), ['name' => 'YouTube review', 'channel' => 'youtube', 'landing_path' => '/features'])
            ->assertRedirect(route('partner.links'));
        $campaign = $partner->referralCampaigns()->sole();
        $this->assertSame('youtube-review', $campaign->slug);
        $this->get(route('partner.links'))->assertSee('/r/'.$partner->referralCode().'?c=youtube-review', false);

        auth()->logout();

        $click = $this->get('/r/'.$partner->referralCode().'?c=youtube-review');
        $click->assertRedirect(url('/features').'?utm_campaign=youtube-review');
        $this->assertSame($campaign->id, $partner->referralClicks()->sole()->referral_campaign_id);
        $campaignCookie = collect($click->headers->getCookies())->first(fn ($c) => $c->getName() === config('referrals.campaign_cookie_name'));
        $this->assertNotNull($campaignCookie);

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/fake']])]);
        $this->withCookie(config('referrals.cookie_name'), $partner->referralCode())
            ->withCookie(config('referrals.campaign_cookie_name'), (string) $campaign->id)
            ->post(route('registration.store', $plan), [
                'name' => 'New Customer', 'email' => 'customer@example.com',
                'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
                'billing_cycle' => 'monthly', 'accepts_refund_policy' => '1',
            ])->assertRedirect();

        $pending = PendingSignup::where('email', 'customer@example.com')->firstOrFail();
        $this->assertSame($campaign->id, $pending->referral_campaign_id);
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();

        Http::fake(['api.flutterwave.com/v3/transactions/*' => Http::response(['status' => 'success', 'data' => [
            'id' => 4455, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful', 'amount' => 67.00, 'currency' => 'USD',
            'customer' => ['email' => 'customer@example.com'],
            'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
        ]])]);
        $this->get(route('registration.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => 4455, 'status' => 'successful']));

        $referral = Referral::where('referrer_id', $partner->id)->sole();
        $this->assertSame($campaign->id, $referral->referral_campaign_id);
        $this->assertSame((int) round($transaction->fresh()->amount_cents * 0.30), $referral->events()->sole()->amount_cents);

        auth()->logout();
        $this->actingAs($partner)->get(route('partner.dashboard'))->assertOk()->assertSee('YouTube review');
    }

    public function test_customers_earn_the_customer_rate(): void
    {
        SiteSetting::set(ReferralProgram::PARTNER_RATE_SETTING, '30');
        SiteSetting::set(ReferralProgram::CUSTOMER_RATE_SETTING, '12.5');

        $this->assertSame(0.125, ReferralProgram::rateFor(User::factory()->make()));
        $this->assertSame(0.3, ReferralProgram::rateFor(User::factory()->make(['is_affiliate_only' => true])));
    }

    public function test_referral_links_ignore_unsafe_landing_paths(): void
    {
        $partner = $this->partner();

        $this->get('/r/'.$partner->referralCode().'?to=//evil.example.com')->assertRedirect(route('registration.pricing'));
        $this->get('/r/'.$partner->referralCode().'?to=https://evil.example.com')->assertRedirect(route('registration.pricing'));
        $this->get('/r/'.$partner->referralCode().'?to=/features/offer-research')->assertRedirect(url('/features/offer-research'));
    }

    public function test_partners_can_only_delete_their_own_campaigns(): void
    {
        $partner = $this->partner();
        $other = ReferralCampaign::factory()->create();

        $this->actingAs($partner)->delete(route('partner.campaigns.destroy', $other))->assertNotFound();
        $this->assertModelExists($other);
    }

    public function test_the_set_password_link_opens_the_partner_portal(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera', 'email' => 'jamie@example.com', 'promotion_channels' => 'YouTube', 'status' => 'pending', 'applied_at' => now(),
        ]);
        app(AffiliateApplicationService::class)->approve($application, $admin);

        $url = null;
        Notification::assertSentTo($application->fresh()->approvedUser, AffiliateApplicationApproved::class, function ($n) use (&$url) {
            $url = $n->setPasswordUrl;

            return true;
        });
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->post(route('affiliate.set-password.store', $application), ['token' => $query['token'], 'password' => 'new-partner-pass-9', 'password_confirmation' => 'new-partner-pass-9'])
            ->assertRedirect(route('partner.dashboard'));
    }

    public function test_admins_set_each_programs_commission_and_the_leaderboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(AffiliateProgramSettings::class)
            ->assertFormSet(['partner_percent' => 20.0, 'customer_percent' => 20.0, 'minimum_payout_usd' => 50])
            ->fillForm(['partner_percent' => 25, 'customer_percent' => 15, 'minimum_payout_usd' => 30, 'leaderboard_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(25.0, ReferralProgram::partnerPercent());
        $this->assertSame(15.0, ReferralProgram::customerPercent());
        $this->assertSame(3000, ReferralProgram::minimumPayoutUsdCents());
        $this->assertTrue(ReferralProgram::leaderboardEnabled());
        $this->get(route('affiliate.landing'))->assertSee('25% recurring income');
    }

    public function test_admins_post_news_and_email_it_to_partners(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $partner = $this->partner();
        $customer = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreatePartnerAnnouncement::class)
            ->fillForm(['title' => 'New gold banners', 'body' => '<p>Fresh designs are in your portal.</p>', 'published_at' => now()->subMinute()])
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = PartnerAnnouncement::sole();
        $this->assertSame($admin->id, $announcement->created_by_id);

        Livewire::actingAs($admin)
            ->test(ListPartnerAnnouncements::class)
            ->callTableAction('emailPartners', $announcement);

        Notification::assertSentTo($partner, PartnerAnnouncementPublished::class);
        Notification::assertNotSentTo($customer, PartnerAnnouncementPublished::class);
        $this->assertNotNull($announcement->fresh()->emailed_at);
    }
}
