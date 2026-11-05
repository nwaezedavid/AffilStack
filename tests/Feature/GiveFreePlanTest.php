<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admins can give a review or test account (for Google, Meta or TikTok
 * reviewers) a plan without payment; paid plans are never touched.
 */
class GiveFreePlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'super-admin']);
    }

    public function test_an_admin_gives_a_test_account_a_free_plan_with_its_credits(): void
    {
        $plan = Plan::factory()->create(['credits_per_month' => 500]);
        $tester = User::factory()->unverified()->create();

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callAction(TestAction::make('giveFreePlan')->table($tester), ['plan_id' => $plan->id, 'months' => 2])
            ->assertNotified();

        $subscription = $tester->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('manual', $subscription->gateway);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->current_period_end->between(now()->addMonths(2)->subDay(), now()->addMonths(2)->addDay()));
        $this->assertSame(500, (int) $tester->fresh()->credits_balance);
        $this->assertNotNull($tester->fresh()->email_verified_at);
    }

    public function test_a_paid_plan_is_never_replaced(): void
    {
        $plan = Plan::factory()->create();
        $customer = User::factory()->create();
        $paid = Subscription::factory()->create(['user_id' => $customer->id, 'plan_id' => $plan->id, 'status' => 'active', 'gateway' => 'paystack']);

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callAction(TestAction::make('giveFreePlan')->table($customer), ['plan_id' => $plan->id, 'months' => 1]);

        $this->assertSame('active', $paid->fresh()->status);
        $this->assertSame(1, $customer->subscriptions()->count());
    }
}
