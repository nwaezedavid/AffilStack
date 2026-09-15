<?php

namespace Tests\Feature;

use App\Filament\Resources\AgentTasks\Pages\ListAgentTasks;
use App\Filament\Resources\SecurityFindings\Pages\ListSecurityFindings;
use App\Models\AgentTask;
use App\Models\SecurityFinding;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityCenterAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_security_center_page_renders_for_an_admin(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);
        SecurityFinding::factory()->create(['title' => 'A test finding']);

        Livewire::actingAs($admin)
            ->test(ListSecurityFindings::class)
            ->assertOk()
            ->assertSeeText('A test finding');
    }

    public function test_the_agent_task_log_page_renders_for_an_admin(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);
        AgentTask::factory()->create(['title' => 'A test task']);

        Livewire::actingAs($admin)
            ->test(ListAgentTasks::class)
            ->assertOk()
            ->assertSeeText('A test task');
    }
}
