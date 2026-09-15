<?php

namespace Tests\Feature;

use App\Filament\Resources\CreativeTasks\Pages\ListCreativeTasks;
use App\Models\AgentTask;
use App\Models\User;
use App\Services\Agents\TonyAgentService;
use App\Services\AI\AIProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The whole point of Tony's admin UI: an admin describes what they want in
 * plain language, previews the result, and only a super-admin can actually
 * publish it — no code required anywhere in this flow.
 */
class CreativeStudioAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(['admin', 'super-admin']);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return ['question' => 'Do you offer refunds?', 'answer' => 'Yes, within 14 days.', 'category' => 'billing'];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    public function test_an_admin_can_request_a_new_creative_draft(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListCreativeTasks::class)
            ->callAction('requestDraft', data: [
                'kind' => TonyAgentService::KIND_FAQ_ITEM,
                'brief' => 'add a refund FAQ',
            ]);

        $this->assertDatabaseHas('agent_tasks', [
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => TonyAgentService::KIND_FAQ_ITEM,
            'status' => AgentTask::STATUS_PENDING,
        ]);
    }

    public function test_approve_and_publish_is_hidden_from_a_plain_admin(): void
    {
        $task = AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => TonyAgentService::KIND_FAQ_ITEM,
            'title' => 'FAQ draft',
            'payload' => ['target_id' => null, 'fields' => ['question' => 'Q', 'answer' => 'A', 'category' => 'general']],
            'status' => AgentTask::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListCreativeTasks::class)
            ->assertTableActionHidden('approveAndPublish', $task);

        Livewire::actingAs($this->superAdmin)
            ->test(ListCreativeTasks::class)
            ->assertTableActionVisible('approveAndPublish', $task);
    }

    public function test_a_super_admin_can_approve_and_publish_from_the_table(): void
    {
        $task = AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => TonyAgentService::KIND_FAQ_ITEM,
            'title' => 'FAQ draft',
            'payload' => ['target_id' => null, 'fields' => ['question' => 'Refund policy?', 'answer' => 'Yes.', 'category' => 'billing']],
            'status' => AgentTask::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(ListCreativeTasks::class)
            ->callTableAction('approveAndPublish', $task);

        $this->assertSame(AgentTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertDatabaseHas('faq_items', ['question' => 'Refund policy?']);
    }

    public function test_an_admin_can_decline_a_draft(): void
    {
        $task = AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => TonyAgentService::KIND_FAQ_ITEM,
            'title' => 'FAQ draft',
            'payload' => ['target_id' => null, 'fields' => ['question' => 'Q', 'answer' => 'A', 'category' => 'general']],
            'status' => AgentTask::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListCreativeTasks::class)
            ->callTableAction('decline', $task, data: ['reason' => 'not needed']);

        $task->refresh();
        $this->assertSame(AgentTask::STATUS_DECLINED, $task->status);
        $this->assertSame('not needed', $task->result);
        $this->assertDatabaseCount('faq_items', 0);
    }
}
