<?php

namespace Tests\Feature;

use App\Filament\Resources\SecurityFindings\Pages\ListSecurityFindings;
use App\Models\AgentTask;
use App\Models\SecurityFinding;
use App\Models\User;
use App\Notifications\ScheduledMaintenanceNotice;
use App\Services\Agents\SecurityFindingApprovalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The user's explicit correction on the AI-agents phase: only the
 * super-admin may approve a fix — never a regular admin, even though
 * regular admins can otherwise see the Security Center.
 */
class SecurityFindingApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_approve_and_schedule_a_fixable_finding(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(['admin', 'super-admin']);
        User::factory()->count(3)->create();

        $finding = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]);

        $task = app(SecurityFindingApprovalService::class)->approveAndSchedule(
            $finding,
            $superAdmin,
            now()->addDay(),
            now()->addDay()->addHour(),
        );

        $this->assertSame(AgentTask::STATUS_SCHEDULED, $task->status);
        $this->assertSame($superAdmin->id, $task->approved_by_id);
        $finding->refresh();
        $this->assertSame(SecurityFinding::STATUS_SCHEDULED, $finding->status);
        $this->assertSame($task->id, $finding->agent_task_id);

        // Every user (all 4 — the super-admin plus the 3 others) gets the notice.
        NotificationFacade::assertSentTimes(ScheduledMaintenanceNotice::class, 4);
    }

    public function test_a_regular_admin_cannot_approve_a_fix_even_with_the_service_called_directly(): void
    {
        $this->seed(RolesSeeder::class);
        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('admin');

        $finding = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]);

        $this->expectException(AuthorizationException::class);

        app(SecurityFindingApprovalService::class)->approveAndSchedule(
            $finding,
            $regularAdmin,
            now()->addDay(),
            now()->addDay()->addHour(),
        );

        $this->assertSame(SecurityFinding::STATUS_OPEN, $finding->fresh()->status);
    }

    public function test_a_non_fixable_finding_cannot_be_scheduled_even_by_the_super_admin(): void
    {
        $this->seed(RolesSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(['admin', 'super-admin']);

        $finding = SecurityFinding::factory()->create(['fix_action' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(SecurityFindingApprovalService::class)->approveAndSchedule(
            $finding,
            $superAdmin,
            now()->addDay(),
            now()->addDay()->addHour(),
        );
    }

    public function test_the_approve_button_is_hidden_from_a_regular_admin_in_the_ui_but_visible_to_the_super_admin(): void
    {
        $this->seed(RolesSeeder::class);
        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(['admin', 'super-admin']);

        $finding = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]);

        Livewire::actingAs($regularAdmin)
            ->test(ListSecurityFindings::class)
            ->assertTableActionHidden('approveAndSchedule', $finding);

        Livewire::actingAs($superAdmin)
            ->test(ListSecurityFindings::class)
            ->assertTableActionVisible('approveAndSchedule', $finding);
    }

    public function test_the_approve_button_is_hidden_once_a_finding_has_no_fix_or_is_no_longer_open(): void
    {
        $this->seed(RolesSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(['admin', 'super-admin']);

        $unfixable = SecurityFinding::factory()->create(['fix_action' => null]);
        $alreadyFixed = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
            'status' => SecurityFinding::STATUS_FIXED,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(ListSecurityFindings::class)
            ->assertTableActionHidden('approveAndSchedule', $unfixable)
            ->assertTableActionHidden('approveAndSchedule', $alreadyFixed);
    }

    public function test_dismissing_a_finding_through_the_ui_marks_it_dismissed(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $finding = SecurityFinding::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListSecurityFindings::class)
            ->callTableAction('dismiss', $finding);

        $this->assertSame(SecurityFinding::STATUS_DISMISSED, $finding->fresh()->status);
    }
}
