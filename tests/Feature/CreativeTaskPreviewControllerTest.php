<?php

namespace Tests\Feature;

use App\Models\AgentTask;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tony's (the Creative Agent's) preview route renders a pending task's
 * proposed values through the exact same public templates real visitors
 * see — without ever writing anything to the database.
 */
class CreativeTaskPreviewControllerTest extends TestCase
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

    public function test_a_non_admin_cannot_view_a_preview(): void
    {
        $task = $this->creativeTask('faq_item', ['question' => 'Q', 'answer' => 'A', 'category' => 'general']);

        $this->actingAs(User::factory()->create())
            ->get(route('creative-tasks.preview', $task))
            ->assertForbidden();
    }

    public function test_a_new_site_page_preview_renders_the_draft_content_without_persisting_it(): void
    {
        $task = $this->creativeTask('site_page', ['title' => 'Careers', 'slug' => 'careers', 'meta_description' => 'M', 'content' => '<p>Join us</p>']);

        $this->actingAs($this->admin)
            ->get(route('creative-tasks.preview', $task))
            ->assertOk()
            ->assertSeeText('Careers')
            ->assertSee('Join us', false)
            ->assertSeeText('PREVIEW ONLY');

        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_a_homepage_feature_preview_shows_the_draft_card_alongside_real_ones(): void
    {
        HomepageFeature::create(['title' => 'Real Feature', 'description' => 'Already live.', 'is_active' => true]);
        $task = $this->creativeTask('homepage_feature', ['title' => 'Draft Feature', 'description' => 'Not live yet.']);

        $this->actingAs($this->admin)
            ->get(route('creative-tasks.preview', $task))
            ->assertOk()
            ->assertSeeText('Real Feature')
            ->assertSeeText('Draft Feature');

        $this->assertDatabaseCount('homepage_features', 1);
    }

    public function test_a_faq_item_preview_shows_the_draft_entry_among_published_ones(): void
    {
        FaqItem::create(['question' => 'Existing Q', 'answer' => 'Existing A.', 'is_published' => true]);
        $task = $this->creativeTask('faq_item', ['question' => 'Draft Q', 'answer' => 'Draft A.', 'category' => 'general']);

        $this->actingAs($this->admin)
            ->get(route('creative-tasks.preview', $task))
            ->assertOk()
            ->assertSeeText('Existing Q')
            ->assertSeeText('Draft Q');

        $this->assertDatabaseCount('faq_items', 1);
    }

    public function test_a_branding_preview_overrides_site_settings_only_for_that_request(): void
    {
        SiteSetting::set('footer_text', 'Live footer.');
        $task = $this->creativeTask('branding', ['footer_text' => 'Draft footer.']);

        $this->actingAs($this->admin)
            ->get(route('creative-tasks.preview', $task))
            ->assertOk()
            ->assertSeeText('Draft footer.');

        $this->assertSame('Live footer.', SiteSetting::get('footer_text'));
    }
}
