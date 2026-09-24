<?php

namespace Tests\Feature;

use App\Filament\Pages\Analytics\Overview;
use App\Models\PageView;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "statistics area" overview page (item #4): traffic, sources,
 * popular pages, and paid users by plan, backed by PageView (see its own
 * tests for the beacon that populates it) plus real Subscription/Plan
 * data. Any admin can view it (VisibleToAnyAdmin) — same rationale as the
 * Documentation cluster: it's oversight material, not department-specific.
 */
class AnalyticsOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_an_admin_can_view_the_overview_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Overview::class)->assertSuccessful();
    }

    public function test_an_admin_sub_account_can_view_it_regardless_of_department(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole('admin_sub');
        $subAdmin->syncPermissions([]);

        Livewire::actingAs($subAdmin)->test(Overview::class)->assertSuccessful();
    }

    public function test_a_non_admin_cannot_access_it(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Overview::class)->assertForbidden();
    }

    public function test_stats_and_tables_reflect_real_page_view_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        PageView::factory()->count(3)->create(['path' => 'pricing', 'source' => 'Organic Search', 'duration_seconds' => 60]);
        PageView::factory()->count(2)->create(['path' => 'about', 'source' => 'Direct', 'duration_seconds' => 30]);

        $component = Livewire::actingAs($admin)->test(Overview::class);
        $component->assertSuccessful();

        $instance = $component->instance();
        $stats = collect($instance->stats())->firstWhere('label', 'Page views');
        $this->assertSame('5', $stats['value']);

        $pages = collect($instance->popularPages());
        $this->assertSame('/pricing', $pages->first()['path']);
        $this->assertSame(3, $pages->first()['views']);

        $sources = collect($instance->trafficSources())->keyBy('source');
        $this->assertSame(3, $sources['Organic Search']['views']);
        $this->assertSame(2, $sources['Direct']['views']);
    }

    public function test_paid_users_by_plan_reflects_active_subscriptions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::factory()->create(['name' => 'Growth', 'price_monthly_cents' => 4900]);
        Subscription::factory()->count(2)->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $instance = Livewire::actingAs($admin)->test(Overview::class)->instance();

        $row = collect($instance->paidUsersByPlan())->firstWhere('plan', 'Growth');
        $this->assertNotNull($row);
        $this->assertSame(2, $row['count']);
        $this->assertSame('$98.00', $row['mrr']);
    }

    public function test_setting_an_invalid_period_falls_back_to_thirty_days(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $instance = Livewire::actingAs($admin)->test(Overview::class)
            ->call('setPeriod', 999)
            ->instance();

        $this->assertSame(30, $instance->days);
    }
}
