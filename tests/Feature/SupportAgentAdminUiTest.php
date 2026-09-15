<?php

namespace Tests\Feature;

use App\Filament\Resources\CannedReplies\Pages\ListCannedReplies;
use App\Models\CannedReply;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin-facing half of Sam (the Support Agent)'s template-learning
 * loop: suggested templates stay out of the reply picker and out of normal
 * use until a staff/admin activates them.
 */
class SupportAgentAdminUiTest extends TestCase
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

    public function test_activating_a_suggested_template_makes_it_active(): void
    {
        $suggested = CannedReply::factory()->suggested()->create();

        Livewire::actingAs($this->admin)
            ->test(ListCannedReplies::class)
            ->assertTableActionHidden('activate', CannedReply::factory()->create()) // an already-active reply has nothing to activate
            ->callTableAction('activate', $suggested);

        $this->assertSame(CannedReply::STATUS_ACTIVE, $suggested->fresh()->status);
    }

    public function test_the_reply_picker_query_only_offers_active_canned_replies(): void
    {
        // MessagesRelationManager's canned_reply_id Select options are built
        // from exactly this query — a suggested or archived template must
        // never reach the picker until a staff/admin activates it.
        CannedReply::factory()->create(['title' => 'Active template']);
        CannedReply::factory()->suggested()->create(['title' => 'Suggested template']);
        CannedReply::factory()->create(['title' => 'Archived template', 'status' => CannedReply::STATUS_ARCHIVED]);

        $options = CannedReply::query()->active()->pluck('title', 'id');

        $this->assertContains('Active template', $options);
        $this->assertNotContains('Suggested template', $options);
        $this->assertNotContains('Archived template', $options);
    }
}
