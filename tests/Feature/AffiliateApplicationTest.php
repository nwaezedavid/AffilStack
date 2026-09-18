<?php

namespace Tests\Feature;

use App\Filament\Pages\AffiliateProgramSettings;
use App\Filament\Resources\AffiliateApplications\AffiliateApplicationResource;
use App\Filament\Resources\AffiliateApplications\Pages\ListAffiliateApplications;
use App\Models\AffiliateApplication;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AffiliateApplicationApproved;
use App\Notifications\AffiliateApplicationReceived;
use App\Notifications\AffiliateApplicationRejected;
use App\Services\Referrals\AffiliateApplicationService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Anyone can sign-up to become an affiliate without first becoming a user
 * of the platform. Users are automatically affiliates, but others will have
 * to be manually approved by the admin after they submit their
 * application." See AffiliateApplicationService for the whole lifecycle.
 */
class AffiliateApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_the_public_landing_page_loads(): void
    {
        $this->get(route('affiliate.landing'))->assertSuccessful()->assertSee('affiliate', false);
    }

    public function test_submitting_an_application_creates_it_and_notifies_the_support_address(): void
    {
        Notification::fake();

        $response = $this->post(route('affiliate.apply'), [
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'phone' => '555-1234',
            'website_url' => 'https://jamierivera.example.com',
            'promotion_channels' => 'YouTube channel with 50k subscribers',
            'audience_size' => '10k_100k',
            'experience_level' => 'experienced',
            'message' => 'Excited to promote this!',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $application = AffiliateApplication::where('email', 'jamie@example.com')->first();
        $this->assertNotNull($application);
        $this->assertTrue($application->isPending());
        $this->assertNotNull($application->applied_at);

        Notification::assertSentOnDemand(AffiliateApplicationReceived::class);
    }

    public function test_someone_who_already_has_an_account_is_told_theyre_already_an_affiliate(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('affiliate.apply'), [
            'name' => 'Existing Person',
            'email' => 'existing@example.com',
            'promotion_channels' => 'Blog',
            'audience_size' => 'under_1k',
            'experience_level' => 'new',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, AffiliateApplication::where('email', 'existing@example.com')->count());
    }

    public function test_a_duplicate_pending_application_is_rejected(): void
    {
        AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        $response = $this->post(route('affiliate.apply'), [
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube again',
            'audience_size' => 'under_1k',
            'experience_level' => 'new',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, AffiliateApplication::where('email', 'jamie@example.com')->count());
    }

    public function test_approving_an_application_creates_an_affiliate_only_account_and_emails_a_set_password_link(): void
    {
        Notification::fake();

        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        $user = app(AffiliateApplicationService::class)->approve($application, $this->admin);

        $this->assertTrue($user->isAffiliateOnly());
        $this->assertTrue($user->hasRole('user'));
        $this->assertSame('jamie@example.com', $user->email);

        $application->refresh();
        $this->assertTrue($application->isApproved());
        $this->assertSame($user->id, $application->approved_user_id);
        $this->assertSame($this->admin->id, $application->reviewed_by_id);
        $this->assertNotNull($application->set_password_token);

        Notification::assertSentTo($user, AffiliateApplicationApproved::class);
    }

    public function test_rejecting_an_application_emails_the_applicant_with_the_reason(): void
    {
        Notification::fake();

        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        app(AffiliateApplicationService::class)->reject($application, $this->admin, 'Not a good fit right now.');

        $application->refresh();
        $this->assertTrue($application->isRejected());
        $this->assertSame('Not a good fit right now.', $application->rejection_reason);
        $this->assertNull($application->approved_user_id);

        Notification::assertSentOnDemand(AffiliateApplicationRejected::class);
    }

    public function test_an_already_reviewed_application_cannot_be_approved_or_rejected_again(): void
    {
        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'approved',
            'applied_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(AffiliateApplicationService::class)->approve($application, $this->admin);
    }

    public function test_the_set_password_link_lets_the_new_affiliate_set_a_password_and_logs_them_in(): void
    {
        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        Notification::fake();
        app(AffiliateApplicationService::class)->approve($application, $this->admin);
        $application->refresh();

        // Recover the plaintext token the same way the notification would
        // have carried it, by re-deriving the URL the service builds.
        $capturedUrl = null;
        Notification::assertSentTo($application->approvedUser, AffiliateApplicationApproved::class, function ($notification) use (&$capturedUrl) {
            $capturedUrl = $notification->setPasswordUrl;

            return true;
        });

        $this->get($capturedUrl)->assertSuccessful();

        $query = [];
        parse_str((string) parse_url($capturedUrl, PHP_URL_QUERY), $query);

        $response = $this->post(route('affiliate.set-password.store', $application), [
            'token' => $query['token'],
            'password' => 'a-very-strong-password-123',
            'password_confirmation' => 'a-very-strong-password-123',
        ]);

        $response->assertRedirect(route('referrals.index'));
        $this->assertAuthenticatedAs($application->approvedUser->fresh());

        $application->refresh();
        $this->assertNull($application->set_password_token);

        // The link is single-use — trying it again must fail.
        $this->post(route('affiliate.set-password.store', $application), [
            'token' => $query['token'],
            'password' => 'another-password-456',
            'password_confirmation' => 'another-password-456',
        ])->assertRedirect(route('affiliate.landing'));
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'approved',
            'applied_at' => now(),
            'set_password_token' => hash('sha256', 'real-token'),
            'set_password_expires_at' => now()->addDays(7),
        ]);

        $this->get(route('affiliate.set-password.show', ['application' => $application->id, 'token' => 'wrong-token']))
            ->assertRedirect(route('affiliate.landing'));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $application = AffiliateApplication::create([
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'status' => 'approved',
            'applied_at' => now(),
            'set_password_token' => hash('sha256', 'real-token'),
            'set_password_expires_at' => now()->subDay(),
        ]);

        $this->get(route('affiliate.set-password.show', ['application' => $application->id, 'token' => 'real-token']))
            ->assertRedirect(route('affiliate.landing'));
    }

    public function test_an_affiliate_only_account_can_reach_referrals_but_nothing_else(): void
    {
        $user = User::factory()->create(['is_affiliate_only' => true]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('referrals.index'))->assertSuccessful();
        $this->actingAs($user)->get(route('offers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('billing.index'))->assertForbidden();
    }

    public function test_an_affiliate_only_account_is_redirected_from_the_dashboard_to_referrals(): void
    {
        $user = User::factory()->create(['is_affiliate_only' => true]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('referrals.index'));
    }

    public function test_a_normal_user_is_unaffected_by_the_affiliate_only_restriction(): void
    {
        $user = User::factory()->create(['is_affiliate_only' => false]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('offers.index'))->assertSuccessful();
    }

    /**
     * "I believe they will need a separate dashboard, different from the
     * users who are actual paid members" — the affiliate-only account
     * shares the exact same routes and layout file as a paying customer,
     * but renders a distinctly-branded "Partner Portal" shell instead of
     * the customer dashboard, so the two are never visually interchangeable.
     */
    public function test_an_affiliate_only_account_sees_the_distinct_partner_portal_branding(): void
    {
        $affiliate = User::factory()->create(['is_affiliate_only' => true]);
        $affiliate->assignRole('user');

        $response = $this->actingAs($affiliate)->get(route('referrals.index'));

        $response->assertSuccessful();
        $response->assertSee('Partner Portal');
        $response->assertDontSee('Credits', false);
    }

    public function test_a_paying_customer_does_not_see_the_partner_portal_branding(): void
    {
        $customer = User::factory()->create(['is_affiliate_only' => false]);
        $customer->assignRole('user');

        $response = $this->actingAs($customer)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertDontSee('Partner Portal');
    }

    public function test_an_admin_can_approve_and_reject_applications_from_the_filament_table(): void
    {
        Notification::fake();

        $pending = AffiliateApplication::create([
            'name' => 'Approve Me',
            'email' => 'approve@example.com',
            'promotion_channels' => 'TikTok',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        $toReject = AffiliateApplication::create([
            'name' => 'Reject Me',
            'email' => 'reject@example.com',
            'promotion_channels' => 'Instagram',
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListAffiliateApplications::class)
            ->callTableAction('approve', $pending);

        $this->assertTrue($pending->fresh()->isApproved());

        Livewire::actingAs($this->admin)
            ->test(ListAffiliateApplications::class)
            ->callTableAction('reject', $toReject, data: ['reason' => 'Not a fit']);

        $this->assertTrue($toReject->fresh()->isRejected());
        $this->assertSame('Not a fit', $toReject->fresh()->rejection_reason);
    }

    public function test_a_department_scoped_sub_account_without_billing_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(AffiliateApplicationResource::canAccess());

        $subAccount->syncPermissions(['department.billing']);
        $this->assertTrue(AffiliateApplicationResource::canAccess());
    }

    /**
     * "If for any reason in the future I decided to discontinue the
     * in-house affiliate program and switch to external network like
     * Partnerstack, I should be able to toggle the feature off from my
     * dashboard and remove it from the menu." See
     * AffiliateProgramSettings/SiteSetting::flag('affiliate_program_enabled').
     */
    public function test_the_menu_link_is_hidden_once_the_program_is_turned_off(): void
    {
        $this->get('/')->assertSee('Affiliate Program');

        SiteSetting::setFlag('affiliate_program_enabled', false);

        $this->get('/')->assertDontSee('Affiliate Program');
    }

    public function test_the_public_landing_page_redirects_home_once_turned_off(): void
    {
        SiteSetting::setFlag('affiliate_program_enabled', false);

        $this->get(route('affiliate.landing'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');
    }

    public function test_a_direct_post_to_apply_is_rejected_once_turned_off(): void
    {
        SiteSetting::setFlag('affiliate_program_enabled', false);

        $response = $this->post(route('affiliate.apply'), [
            'name' => 'Jamie Rivera',
            'email' => 'jamie@example.com',
            'promotion_channels' => 'YouTube',
            'audience_size' => 'under_1k',
            'experience_level' => 'new',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, AffiliateApplication::where('email', 'jamie@example.com')->count());
    }

    public function test_the_sitemap_drops_the_landing_page_once_turned_off(): void
    {
        SiteSetting::setFlag('affiliate_program_enabled', false);

        $this->get('/sitemap.xml')->assertDontSee(route('affiliate.landing'), false);
    }

    public function test_an_already_approved_affiliate_keeps_full_portal_access_once_the_program_is_turned_off(): void
    {
        SiteSetting::setFlag('affiliate_program_enabled', false);

        $affiliate = User::factory()->create(['is_affiliate_only' => true]);
        $affiliate->assignRole('user');

        $this->actingAs($affiliate)->get(route('referrals.index'))->assertSuccessful();
    }

    public function test_an_admin_can_toggle_the_affiliate_program_from_the_settings_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AffiliateProgramSettings::class)
            ->fillForm(['affiliate_program_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(SiteSetting::flag('affiliate_program_enabled'));

        Livewire::actingAs($this->admin)
            ->test(AffiliateProgramSettings::class)
            ->fillForm(['affiliate_program_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(SiteSetting::flag('affiliate_program_enabled'));
    }

    public function test_a_department_scoped_sub_account_without_billing_access_cannot_reach_the_settings_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(AffiliateProgramSettings::canAccess());

        $subAccount->syncPermissions(['department.billing']);
        $this->assertTrue(AffiliateProgramSettings::canAccess());
    }
}
