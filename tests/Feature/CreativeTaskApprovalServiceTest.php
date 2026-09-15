<?php

namespace Tests\Feature;

use App\Models\AgentTask;
use App\Models\HomepageFeature;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Agents\CreativeTaskApprovalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The one place a pending Tony (the Creative Agent) task actually reaches
 * the live site — gated to the super-admin only, and immediately executed
 * (unlike Tom's scheduled fixes, a content change has no reason to wait).
 */
class CreativeTaskApprovalServiceTest extends TestCase
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
    }

    protected function creativeTask(string $type, array $fields, ?int $targetId = null): AgentTask
    {
        return AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => $type,
            'title' => 'Test creative task',
            'summary' => 'A brief.',
            'payload' => ['target_id' => $targetId, 'fields' => $fields],
            'risk_level' => 'low',
            'status' => AgentTask::STATUS_PENDING,
        ]);
    }

    public function test_a_plain_admin_cannot_approve(): void
    {
        $task = $this->creativeTask('faq_item', ['question' => 'Q', 'answer' => 'A', 'category' => 'general']);

        $this->expectException(AuthorizationException::class);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->admin);
    }

    public function test_only_a_pending_task_can_be_approved(): void
    {
        $task = $this->creativeTask('faq_item', ['question' => 'Q', 'answer' => 'A', 'category' => 'general']);
        $task->update(['status' => AgentTask::STATUS_DECLINED]);

        $this->expectException(InvalidArgumentException::class);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);
    }

    public function test_approving_a_new_faq_entry_publishes_it(): void
    {
        $task = $this->creativeTask('faq_item', ['question' => 'Refunds?', 'answer' => 'Yes.', 'category' => 'billing', 'sort_order' => 1]);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);

        $this->assertDatabaseHas('faq_items', ['question' => 'Refunds?', 'is_published' => true]);
        $task->refresh();
        $this->assertSame(AgentTask::STATUS_COMPLETED, $task->status);
        $this->assertSame($this->superAdmin->id, $task->approved_by_id);
        $this->assertNotNull($task->executed_at);
    }

    public function test_approving_a_revision_updates_the_existing_homepage_feature_in_place(): void
    {
        $feature = HomepageFeature::create(['title' => 'Old', 'description' => 'Old desc.', 'is_active' => true]);
        $task = $this->creativeTask('homepage_feature', ['title' => 'New', 'description' => 'New desc.'], $feature->id);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);

        $this->assertSame('New', $feature->fresh()->title);
        $this->assertDatabaseCount('homepage_features', 1);
    }

    public function test_approving_a_branding_task_writes_every_field_to_site_settings(): void
    {
        $task = $this->creativeTask('branding', ['footer_text' => 'New footer.', 'hero_headline' => 'New headline.']);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);

        $this->assertSame('New footer.', SiteSetting::get('footer_text'));
        $this->assertSame('New headline.', SiteSetting::get('hero_headline'));
    }

    public function test_approving_a_site_page_task_publishes_a_new_page(): void
    {
        $task = $this->creativeTask('site_page', ['title' => 'Careers', 'slug' => 'careers', 'meta_description' => 'Meta', 'content' => '<p>Hi</p>']);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);

        $this->assertDatabaseHas('site_pages', ['slug' => 'careers', 'is_published' => true]);
    }

    public function test_declining_marks_the_task_declined_without_publishing_anything(): void
    {
        $task = $this->creativeTask('faq_item', ['question' => 'Q', 'answer' => 'A', 'category' => 'general']);

        app(CreativeTaskApprovalService::class)->decline($task, $this->admin, 'not on-brand');

        $task->refresh();
        $this->assertSame(AgentTask::STATUS_DECLINED, $task->status);
        $this->assertSame('not on-brand', $task->result);
        $this->assertDatabaseCount('faq_items', 0);
    }

    public function test_an_unknown_creative_task_type_fails_gracefully_instead_of_throwing(): void
    {
        $task = $this->creativeTask('not-a-real-type', []);

        app(CreativeTaskApprovalService::class)->approveAndPublish($task, $this->superAdmin);

        $this->assertSame(AgentTask::STATUS_FAILED, $task->fresh()->status);
    }
}
