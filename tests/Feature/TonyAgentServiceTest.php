<?php

namespace Tests\Feature;

use App\Models\AgentTask;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Agents\TonyAgentService;
use App\Services\AI\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tony, the Creative Agent (AI agents phase, agent #4 of 4): every draft()
 * call only ever creates a pending AgentTask — never touches a content
 * table or SiteSetting directly — and every proposed field is filtered
 * against an explicit allowlist so the AI's raw JSON can never smuggle in
 * an unexpected key (e.g. is_published, or an arbitrary SiteSetting key).
 */
class TonyAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeAi(array $response): void
    {
        $this->app->instance(AIProvider::class, new class($response) implements AIProvider
        {
            public function __construct(protected array $response) {}

            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return $this->response;
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    public function test_drafting_an_unknown_kind_is_rejected(): void
    {
        $this->fakeAi([]);

        $this->expectException(InvalidArgumentException::class);

        app(TonyAgentService::class)->draft('not-a-real-kind', 'do something', User::factory()->create());
    }

    public function test_drafting_a_new_site_page_creates_a_pending_creative_task_with_a_unique_slug(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => '<p>Existing</p>']);

        $this->fakeAi([
            'title' => 'About Us',
            'slug' => 'about', // collides with the existing page on purpose
            'meta_description' => 'Meta',
            'content' => '<p>New content</p>',
            'is_published' => false, // must be filtered out — never trusted from the AI
        ]);

        $admin = User::factory()->create();
        $task = app(TonyAgentService::class)->draft(TonyAgentService::KIND_SITE_PAGE, 'write an about page', $admin);

        $this->assertSame(AgentTask::AGENT_CREATIVE, $task->agent);
        $this->assertSame(TonyAgentService::KIND_SITE_PAGE, $task->type);
        $this->assertSame(AgentTask::STATUS_PENDING, $task->status);
        $this->assertSame($admin->id, $task->requested_by_id);
        $this->assertNull($task->payload['target_id']);
        $this->assertSame('about-2', $task->payload['fields']['slug']);
        $this->assertArrayNotHasKey('is_published', $task->payload['fields']);
        $this->assertDatabaseCount('site_pages', 1); // nothing published yet
    }

    public function test_drafting_a_revision_of_an_existing_site_page_never_changes_its_slug(): void
    {
        $page = SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => '<p>Old</p>']);

        $this->fakeAi(['title' => 'Terms of Service', 'slug' => 'a-different-slug', 'meta_description' => 'Meta', 'content' => '<p>New</p>']);

        $task = app(TonyAgentService::class)->draft(TonyAgentService::KIND_SITE_PAGE, 'tighten this up', User::factory()->create(), $page->id);

        $this->assertSame($page->id, $task->payload['target_id']);
        $this->assertArrayNotHasKey('slug', $task->payload['fields']);
    }

    public function test_drafting_a_new_homepage_feature_defaults_sort_order_to_the_next_slot(): void
    {
        HomepageFeature::create(['title' => 'Existing', 'description' => 'Existing feature.', 'sort_order' => 5]);
        $this->fakeAi(['title' => 'AI Research', 'description' => 'Find your next offer in seconds.', 'icon' => '🔍']);

        $task = app(TonyAgentService::class)->draft(TonyAgentService::KIND_HOMEPAGE_FEATURE, 'add a feature about research', User::factory()->create());

        $this->assertSame(6, $task->payload['fields']['sort_order']);
        $this->assertSame('AI Research', $task->payload['fields']['title']);
    }

    public function test_drafting_a_faq_entry_filters_unexpected_fields(): void
    {
        $this->fakeAi(['question' => 'Do you offer refunds?', 'answer' => 'Yes, within 14 days.', 'category' => 'billing', 'sort_order' => 999]);

        $task = app(TonyAgentService::class)->draft(TonyAgentService::KIND_FAQ_ITEM, 'add a refund FAQ', User::factory()->create());

        $this->assertSame('Do you offer refunds?', $task->payload['fields']['question']);
        // sort_order isn't in the allowed AI-output keys for a new item —
        // it's computed by the service, not trusted from the model.
        $this->assertSame(1, $task->payload['fields']['sort_order']);
    }

    public function test_drafting_branding_only_stores_allowed_keys(): void
    {
        $this->fakeAi([
            'footer_text' => 'New footer.',
            'color_primary' => '#ff0000', // not in the allowlist — must be dropped
            'is_enabled' => true, // nonsense key — must be dropped
        ]);

        $task = app(TonyAgentService::class)->draft(TonyAgentService::KIND_BRANDING, 'refresh the footer', User::factory()->create());

        $this->assertSame(['footer_text' => 'New footer.'], $task->payload['fields']);
        $this->assertNull(SiteSetting::get('footer_text')); // still unpublished
    }
}
