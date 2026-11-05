<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\CreditLedger;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionRenewalReminder;
use App\Services\Payments\AdminPlanAssigner;
use App\Services\Payments\PlanChangeService;
use App\Services\Payments\SubscriptionRenewalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An admin gives a manually added customer one complimentary month on a
 * plan from the Users screen (see AdminPlanAssigner).
 */
class AdminAssignedPlanTest extends TestCase
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
        $this->plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_creating_a_user_with_a_plan_gives_one_free_month_assigned_by_admin(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));

        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Manual Customer',
                'email' => 'manual@example.com',
                'password' => 'a-strong-password',
                'credits_balance' => 0,
                'assign_plan_id' => $this->plan->id,
                'assign_billing_cycle' => 'yearly',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'manual@example.com')->firstOrFail();
        $subscription = $user->activeSubscription;

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->assigned_by_admin);
        $this->assertSame($this->admin->id, $subscription->assigned_by_user_id);
        $this->assertSame('admin', $subscription->gateway);
        $this->assertSame('yearly', $subscription->billing_cycle);
        $this->assertTrue($subscription->current_period_end->equalTo(now()->addMonth()));
        $this->assertSame(600, $user->fresh()->credits_balance);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('reason', 'admin_assigned_grant')->count());
        $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count(), 'Nothing is charged.');
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_editing_a_user_assigns_the_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['assign_plan_id' => $this->plan->id, 'assign_billing_cycle' => 'monthly'])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscription = $user->fresh()->activeSubscription;
        $this->assertTrue($subscription->assigned_by_admin);
        $this->assertSame('monthly', $subscription->billing_cycle);
    }

    public function test_a_paying_customers_subscription_is_never_replaced(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $paid = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'flutterwave',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['assign_plan_id' => $this->plan->id, 'assign_billing_cycle' => 'monthly'])
            ->call('save');

        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame('active', $paid->fresh()->status);
        $this->assertFalse($paid->fresh()->assigned_by_admin);
    }

    public function test_the_month_ends_with_reminders_then_expiry_when_unpaid(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->assignRole('user');
        $subscription = app(AdminPlanAssigner::class)->assign($user, $this->plan, 'monthly', $this->admin);

        $this->travelTo(now()->addMonth()->subDays(2));
        $this->assertSame(1, app(SubscriptionRenewalService::class)->sendRenewalReminders());
        Notification::assertSentTo($user, SubscriptionRenewalReminder::class, function (SubscriptionRenewalReminder $notification) use ($user) {
            return str_contains($notification->toMail($user)->subject, 'complimentary');
        });

        $this->travelTo(now()->addDays(3));
        $this->assertSame(1, app(SubscriptionRenewalService::class)->expireLapsed());
        $this->assertSame('expired', $subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpired::class);
    }

    public function test_a_complimentary_month_earns_no_upgrade_credit(): void
    {
        $user = User::factory()->create();
        $subscription = app(AdminPlanAssigner::class)->assign($user, $this->plan, 'monthly', $this->admin);
        $bigger = Plan::create([
            'name' => 'Scale', 'slug' => 'scale', 'description' => 'Bigger',
            'price_monthly_cents' => 14700, 'price_yearly_cents' => 147000, 'currency' => 'USD',
            'credits_per_month' => 2000, 'active_products_limit' => 20, 'contact_limit' => 20000,
            'team_seats' => 3, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->assertSame(0, app(PlanChangeService::class)->prorationCreditCents($subscription, $bigger, 'monthly', 'paystack'));
    }

    public function test_the_billing_page_shows_the_admin_assignment_and_a_pay_to_keep_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        app(AdminPlanAssigner::class)->assign($user, $this->plan, 'monthly', $this->admin);

        $this->actingAs($user)->get(route('billing.index'))
            ->assertSuccessful()
            ->assertSee('Assigned by admin')
            ->assertSee('Pay to keep this plan');
    }
}
