<?php

namespace Tests\Feature;

use App\Models\BrainAgentSetting;
use App\Models\MarketingCampaign;
use App\Models\Offer;
use App\Models\User;
use App\Services\Agents\BrainAgentService;
use App\Services\AI\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Brain, the Marketing Agent (AI agents phase, agent #3 of 4): drafting an
 * offer into a campaign brief, the one-click approve-and-launch gate (real
 * ad spend requires both connections verified and the master switch on),
 * and the autonomous daily optimization pass over already-live campaigns.
 */
class BrainAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeImageProvider(): void
    {
        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return [];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return 'https://cdn.example.com/generated.png';
            }
        });
    }

    protected function offer(): Offer
    {
        return Offer::create([
            'user_id' => User::factory()->create()->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com/widget',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
            'ideal_customer_summary' => 'DIY hobbyists aged 25-45.',
            'recommended_angle' => 'Saves hours of manual work.',
        ]);
    }

    public function test_drafting_a_campaign_requires_an_anthropic_key_first(): void
    {
        $this->fakeImageProvider();

        $this->expectException(RuntimeException::class);

        app(BrainAgentService::class)->draftCampaign($this->offer(), User::factory()->create());
    }

    public function test_drafting_a_campaign_calls_claude_and_generates_promotional_images(): void
    {
        $this->fakeImageProvider();
        BrainAgentSetting::current()->update(['credentials' => ['anthropic_api_key' => 'sk-ant-test']]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'campaign_title' => 'Acme Widget Launch',
                    'ad_copy_variants' => [['headline' => 'H', 'primary_text' => 'P', 'description' => 'D']],
                    'targeting' => ['age_range' => '25-45', 'interests' => ['DIY'], 'geos' => ['US']],
                    'budget_suggestion' => ['daily_min' => 10, 'daily_max' => 25, 'currency' => 'USD'],
                    'image_prompts' => ['A widget on a workbench.'],
                ])]],
            ]),
        ]);

        $admin = User::factory()->create();
        $campaign = app(BrainAgentService::class)->draftCampaign($this->offer(), $admin, 'maximize signups');

        $this->assertSame(MarketingCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertSame('Acme Widget Launch', $campaign->title);
        $this->assertSame($admin->id, $campaign->created_by_id);
        $this->assertSame(['https://cdn.example.com/generated.png'], $campaign->image_urls);
    }

    public function test_approve_and_launch_refuses_when_brain_is_not_fully_connected(): void
    {
        $campaign = MarketingCampaign::factory()->create();

        $this->expectException(RuntimeException::class);

        app(BrainAgentService::class)->approveAndLaunch($campaign, User::factory()->create());
    }

    public function test_approve_and_launch_refuses_a_campaign_that_is_not_a_draft(): void
    {
        $this->connectBrainFully();
        $campaign = MarketingCampaign::factory()->running()->create();

        $this->expectExceptionMessage('Only a draft campaign can be approved and launched.');

        app(BrainAgentService::class)->approveAndLaunch($campaign, User::factory()->create());
    }

    public function test_approve_and_launch_marks_the_campaign_running_on_success(): void
    {
        $this->connectBrainFully();
        $campaign = MarketingCampaign::factory()->create();

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Launched campaign act_998877 successfully.']],
            ]),
        ]);

        $admin = User::factory()->create();
        app(BrainAgentService::class)->approveAndLaunch($campaign, $admin);

        $campaign->refresh();
        $this->assertSame(MarketingCampaign::STATUS_RUNNING, $campaign->status);
        $this->assertSame($admin->id, $campaign->approved_by_id);
        $this->assertNotNull($campaign->approved_at);
        $this->assertSame('act_998877', $campaign->meta_campaign_ref);
    }

    public function test_approve_and_launch_marks_the_campaign_failed_when_claude_reports_a_tool_error(): void
    {
        $this->connectBrainFully();
        $campaign = MarketingCampaign::factory()->create();

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'mcp_tool_result', 'is_error' => true, 'text' => 'Meta rejected the ad account.']],
            ]),
        ]);

        app(BrainAgentService::class)->approveAndLaunch($campaign, User::factory()->create());

        $campaign->refresh();
        $this->assertSame(MarketingCampaign::STATUS_FAILED, $campaign->status);
        $this->assertNotNull($campaign->failure_reason);
    }

    public function test_optimize_running_campaigns_does_nothing_when_brain_is_not_fully_connected(): void
    {
        MarketingCampaign::factory()->running()->create();

        $processed = app(BrainAgentService::class)->optimizeRunningCampaigns();

        $this->assertSame(0, $processed);
    }

    public function test_optimize_running_campaigns_reviews_every_live_campaign_and_isolates_failures(): void
    {
        $this->connectBrainFully();
        MarketingCampaign::factory()->running()->create();
        MarketingCampaign::factory()->running()->create();
        MarketingCampaign::factory()->create(['status' => MarketingCampaign::STATUS_DRAFT]); // not eligible

        Http::fakeSequence()
            ->push(['content' => [['type' => 'text', 'text' => 'Everything looks efficient — no changes made.']]])
            ->push(['error' => ['message' => 'temporary outage']], 500);

        $processed = app(BrainAgentService::class)->optimizeRunningCampaigns();

        $this->assertSame(2, $processed);
        $this->assertDatabaseCount('marketing_campaigns', 3);
    }

    protected function connectBrainFully(): void
    {
        BrainAgentSetting::current()->update([
            'is_enabled' => true,
            'credentials' => [
                'anthropic_api_key' => 'sk-ant-test',
                'meta_mcp_url' => 'https://mcp.example.com/meta-ads',
                'meta_mcp_token' => 'mcp-token',
            ],
            'anthropic_verification_status' => 'success',
            'meta_mcp_verification_status' => 'success',
        ]);
    }
}
