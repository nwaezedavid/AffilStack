<?php

namespace Tests\Feature;

use App\Filament\Pages\FundingHealth;
use App\Models\HeyGenSetting;
use App\Models\IntegrationFundingSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\FundingAlert;
use App\Services\Referrals\PayoutWalletService;
use App\Services\Settings\FundingHealthChecker;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vault, the funding-monitor agent — "an AI Agent in the admin dashboard
 * area that will monitor all the third-party APIs and platforms that
 * requires... to find the account or buy more credit, and then remind me
 * on time to recharge or fund my account... to ensure that my users will
 * not get interrupted". See FundingHealthChecker for what each item checks
 * and how repeat notifications are avoided.
 */
class FundingHealthTest extends TestCase
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

    public function test_heygen_low_balance_notifies_every_full_admin_exactly_once(): void
    {
        Notification::fake();

        HeyGenSetting::current()->update(['credentials' => ['api_key' => 'hg_test_fake']]);
        IntegrationFundingSetting::current()->update(['heygen_low_balance_threshold' => 100]);

        Http::fake([
            'api.heygen.com/*' => Http::response(['data' => ['remaining_quota' => 42]], 200),
        ]);

        app(FundingHealthChecker::class)->runChecks();

        Notification::assertSentTo($this->admin, FundingAlert::class, fn (FundingAlert $n) => str_contains($n->title, 'HeyGen'));

        $funding = IntegrationFundingSetting::current();
        $this->assertTrue($funding->isHeyGenBalanceLow());
        $this->assertNotNull($funding->heygen_low_balance_notified_at);

        // A second check while still low must not notify again.
        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertNothingSent();
    }

    public function test_heygen_balance_recovering_above_threshold_clears_the_notified_flag_and_can_notify_again_later(): void
    {
        HeyGenSetting::current()->update(['credentials' => ['api_key' => 'hg_test_fake']]);
        IntegrationFundingSetting::current()->update(['heygen_low_balance_threshold' => 100]);

        // A single fake that reads a mutable variable by reference, since
        // Http::fake() only ever honors the FIRST registered stub for a
        // given URL pattern within one test — a second Http::fake() call
        // for the same host is silently never reached, so this is the only
        // way to change what a mocked endpoint returns mid-test. Note the
        // explicit "use (&$remaining)": an arrow function captures by
        // value, so it would freeze the quota at whatever it was when the
        // fake was declared.
        $remaining = 10;
        Http::fake(function () use (&$remaining) {
            return Http::response(['data' => ['remaining_quota' => $remaining]], 200);
        });

        app(FundingHealthChecker::class)->runChecks();
        $this->assertNotNull(IntegrationFundingSetting::current()->heygen_low_balance_notified_at);

        $remaining = 500;
        app(FundingHealthChecker::class)->runChecks();
        $this->assertFalse(IntegrationFundingSetting::current()->isHeyGenBalanceLow());
        $this->assertNull(IntegrationFundingSetting::current()->heygen_low_balance_notified_at);

        $remaining = 10;
        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertSentTo($this->admin, FundingAlert::class);
    }

    public function test_heygen_not_configured_is_skipped_without_error_or_notification(): void
    {
        Notification::fake();

        app(FundingHealthChecker::class)->runChecks();

        Notification::assertNothingSent();
        $this->assertNull(IntegrationFundingSetting::current()->heygen_balance_checked_at);
    }

    public function test_wallet_currency_below_threshold_notifies_once_and_recovering_allows_a_future_notification(): void
    {
        IntegrationFundingSetting::current()->update(['wallet_thresholds_cents' => ['USD' => 10000]]);

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertSentTo($this->admin, FundingAlert::class, fn (FundingAlert $n) => str_contains($n->title, 'wallet'));
        $this->assertTrue(IntegrationFundingSetting::current()->hasNotifiedWalletLowBalance('USD'));

        // Still below threshold — must not notify again.
        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertNothingSent();

        // Top up above the threshold — clears the flag.
        app(PayoutWalletService::class)->topUp('USD', 20000, $this->admin);
        app(FundingHealthChecker::class)->runChecks();
        $this->assertFalse(IntegrationFundingSetting::current()->hasNotifiedWalletLowBalance('USD'));

        // Drawing it back down below threshold notifies again.
        WalletTransaction::create(['currency' => 'USD', 'type' => 'payout', 'amount_cents' => -15000]);
        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertSentTo($this->admin, FundingAlert::class);
    }

    public function test_a_currency_with_no_threshold_set_is_never_checked_or_notified(): void
    {
        app(PayoutWalletService::class)->topUp('USD', 100, $this->admin);

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();

        Notification::assertNothingSent();
    }

    public function test_anthropic_reminder_falls_due_after_the_configured_days_and_notifies_once(): void
    {
        IntegrationFundingSetting::current()->update([
            'anthropic_reminder_days' => 14,
            'anthropic_reminder_last_acknowledged_at' => now()->subDays(15),
        ]);

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertSentTo($this->admin, FundingAlert::class, fn (FundingAlert $n) => str_contains($n->title, 'Anthropic'));

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertNothingSent();
    }

    public function test_anthropic_reminder_not_yet_due_sends_nothing(): void
    {
        IntegrationFundingSetting::current()->update([
            'anthropic_reminder_days' => 14,
            'anthropic_reminder_last_acknowledged_at' => now()->subDays(2),
        ]);

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();

        Notification::assertNothingSent();
    }

    public function test_meta_ads_reminder_falls_due_and_notifies_once(): void
    {
        IntegrationFundingSetting::current()->update([
            'meta_ads_reminder_days' => 14,
            'meta_ads_reminder_last_acknowledged_at' => now()->subDays(20),
        ]);

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertSentTo($this->admin, FundingAlert::class, fn (FundingAlert $n) => str_contains($n->title, 'Meta ad'));

        Notification::fake();
        app(FundingHealthChecker::class)->runChecks();
        Notification::assertNothingSent();
    }

    public function test_admin_can_acknowledge_a_manual_reminder_from_the_page_and_it_stops_being_due(): void
    {
        IntegrationFundingSetting::current()->update([
            'anthropic_reminder_days' => 14,
            'anthropic_reminder_last_acknowledged_at' => now()->subDays(20),
            'anthropic_reminder_notified_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(FundingHealth::class)
            ->call('acknowledgeReminder', 'anthropic')
            ->assertSuccessful();

        $funding = IntegrationFundingSetting::current();
        $this->assertFalse($funding->isAnthropicReminderDue());
        $this->assertNull($funding->anthropic_reminder_notified_at);
    }

    public function test_admin_can_update_thresholds_and_cadences_from_the_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FundingHealth::class)
            ->fillForm([
                'heygen_low_balance_threshold' => 250,
                'anthropic_reminder_days' => 7,
                'meta_ads_reminder_days' => 10,
                'wallet_thresholds' => [
                    ['currency' => 'USD', 'amount' => 150.50],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $funding = IntegrationFundingSetting::current();
        $this->assertSame(250, $funding->heygen_low_balance_threshold);
        $this->assertSame(7, $funding->anthropic_reminder_days);
        $this->assertSame(10, $funding->meta_ads_reminder_days);
        $this->assertSame(15050, $funding->wallet_thresholds_cents['USD']);
    }

    public function test_a_full_admin_can_reach_the_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FundingHealth::class)
            ->assertSuccessful();
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(FundingHealth::class)
            ->assertForbidden();
    }

    public function test_a_department_scoped_sub_account_without_ai_agents_access_cannot_reach_the_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(FundingHealth::canAccess());

        $subAccount->syncPermissions(['department.ai_agents']);
        $this->assertTrue(FundingHealth::canAccess());
    }

    public function test_items_reports_manual_kind_items_with_why_they_cant_be_live_checked(): void
    {
        $items = app(FundingHealthChecker::class)->items();

        foreach (['anthropic', 'meta_ads'] as $key) {
            $item = collect($items)->firstWhere('key', $key);
            $this->assertSame('manual', $item['kind']);
            $this->assertNotEmpty($item['why_manual']);
        }
    }

    public function test_the_scheduled_command_delegates_to_the_checker(): void
    {
        HeyGenSetting::current()->update(['credentials' => ['api_key' => 'hg_test_fake']]);
        Http::fake(['api.heygen.com/*' => Http::response(['data' => ['remaining_quota' => 999]], 200)]);

        $this->artisan('integrations:check-funding-health')->assertSuccessful();

        $this->assertNotNull(IntegrationFundingSetting::current()->heygen_balance_checked_at);
    }
}
