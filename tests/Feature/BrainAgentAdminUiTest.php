<?php

namespace Tests\Feature;

use App\Filament\Pages\BrainAgentSettings;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Models\BrainAgentSetting;
use App\Models\MarketingCampaign;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The whole point of Brain's admin UI: an admin connects their own
 * Anthropic key and their own Meta Ads MCP server, verifies each with a
 * click, then drafts and approves a campaign — no code required anywhere
 * in this flow.
 */
class BrainAgentAdminUiTest extends TestCase
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

    public function test_admin_can_save_brain_credentials_from_the_settings_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BrainAgentSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'anthropic_api_key' => 'sk-ant-secret',
                'meta_mcp_url' => 'https://mcp.example.com/meta-ads',
                'meta_mcp_token' => 'mcp-token-secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = BrainAgentSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('sk-ant-secret', $settings->credential('anthropic_api_key'));
        $this->assertSame('https://mcp.example.com/meta-ads', $settings->credential('meta_mcp_url'));

        // Encrypted at rest.
        $raw = \DB::table('brain_agent_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('sk-ant-secret', (string) $raw);
    }

    public function test_verify_anthropic_persists_the_form_then_records_the_result(): void
    {
        Http::fake(['api.anthropic.com/v1/models' => Http::response(['data' => []], 200)]);

        Livewire::actingAs($this->admin)
            ->test(BrainAgentSettings::class)
            ->fillForm(['anthropic_api_key' => 'sk-ant-good'])
            ->call('verifyAnthropic');

        $settings = BrainAgentSetting::current();
        $this->assertSame('success', $settings->anthropic_verification_status);
        $this->assertNotNull($settings->anthropic_verified_at);
    }

    public function test_verify_meta_mcp_records_a_failed_connection(): void
    {
        Http::fake(['api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'bad mcp url']], 400)]);

        Livewire::actingAs($this->admin)
            ->test(BrainAgentSettings::class)
            ->fillForm([
                'anthropic_api_key' => 'sk-ant-good',
                'meta_mcp_url' => 'https://mcp.example.com/broken',
            ])
            ->call('verifyMetaMcp');

        $settings = BrainAgentSetting::current();
        $this->assertSame('failed', $settings->meta_mcp_verification_status);
        $this->assertStringContainsString('bad mcp url', $settings->meta_mcp_verification_message);
    }

    public function test_admin_can_draft_a_campaign_from_the_list_page(): void
    {
        BrainAgentSetting::current()->update(['credentials' => ['anthropic_api_key' => 'sk-ant-test']]);
        $offer = Offer::create([
            'user_id' => User::factory()->create()->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com/widget',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode(['campaign_title' => 'Acme Launch'])]],
            ]),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListMarketingCampaigns::class)
            ->callAction('draftCampaign', data: ['offer_id' => $offer->id, 'goal' => 'signups']);

        $this->assertDatabaseHas('marketing_campaigns', ['title' => 'Acme Launch', 'offer_id' => $offer->id]);
    }

    public function test_approve_and_launch_is_hidden_until_brain_is_fully_connected(): void
    {
        $campaign = MarketingCampaign::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(ListMarketingCampaigns::class)
            ->assertTableActionHidden('approveAndLaunch', $campaign);

        BrainAgentSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['anthropic_api_key' => 'a', 'meta_mcp_url' => 'https://mcp.example.com', 'meta_mcp_token' => 't'],
            'anthropic_verification_status' => 'success',
            'meta_mcp_verification_status' => 'success',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListMarketingCampaigns::class)
            ->assertTableActionVisible('approveAndLaunch', $campaign);
    }

    public function test_approve_and_launch_action_launches_the_campaign(): void
    {
        BrainAgentSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['anthropic_api_key' => 'a', 'meta_mcp_url' => 'https://mcp.example.com', 'meta_mcp_token' => 't'],
            'anthropic_verification_status' => 'success',
            'meta_mcp_verification_status' => 'success',
        ]);
        $campaign = MarketingCampaign::factory()->create();

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Launched campaign act_112233.']],
            ]),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListMarketingCampaigns::class)
            ->callTableAction('approveAndLaunch', $campaign);

        $this->assertSame(MarketingCampaign::STATUS_RUNNING, $campaign->fresh()->status);
    }
}
