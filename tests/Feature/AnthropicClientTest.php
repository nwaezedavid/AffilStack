<?php

namespace Tests\Feature;

use App\Services\AI\AIGenerationException;
use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Brain's (the Marketing Agent's) direct line to Anthropic's Messages API —
 * kept separate from the app-wide (OpenAI-backed) AIProvider since it runs
 * on the admin's own Anthropic account. Covers plain JSON generation, key
 * verification, and the MCP-connector call that actually lets Claude use a
 * connected Meta Ads MCP server's tools.
 */
class AnthropicClientTest extends TestCase
{
    public function test_generate_json_parses_the_first_text_block_as_json(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"campaign_title": "Great Deal"}']],
            ]),
        ]);

        $client = new AnthropicClient('sk-ant-test', 'claude-sonnet-5');
        $result = $client->generateJson('system', 'user');

        $this->assertSame(['campaign_title' => 'Great Deal'], $result);
    }

    public function test_generate_json_strips_markdown_code_fences_before_decoding(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => "```json\n{\"ok\": true}\n```"]],
            ]),
        ]);

        $result = (new AnthropicClient('sk-ant-test', 'claude-sonnet-5'))->generateJson('system', 'user');

        $this->assertSame(['ok' => true], $result);
    }

    public function test_generate_json_throws_when_the_response_is_not_valid_json(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'not json at all']],
            ]),
        ]);

        $this->expectException(AIGenerationException::class);

        (new AnthropicClient('sk-ant-test', 'claude-sonnet-5'))->generateJson('system', 'user');
    }

    public function test_generate_json_throws_when_the_request_fails(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'bad key']], 401),
        ]);

        $this->expectException(AIGenerationException::class);

        (new AnthropicClient('sk-ant-bad', 'claude-sonnet-5'))->generateJson('system', 'user');
    }

    public function test_verify_api_key_succeeds_when_anthropic_accepts_the_key(): void
    {
        Http::fake(['api.anthropic.com/v1/models' => Http::response(['data' => []], 200)]);

        $result = (new AnthropicClient('sk-ant-good', 'claude-sonnet-5'))->verifyApiKey();

        $this->assertTrue($result['success']);
    }

    public function test_verify_api_key_fails_and_surfaces_anthropics_error_message(): void
    {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401),
        ]);

        $result = (new AnthropicClient('sk-ant-bad', 'claude-sonnet-5'))->verifyApiKey();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid x-api-key', $result['message']);
    }

    public function test_running_an_agentic_campaign_action_attaches_the_mcp_server_and_reports_success(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Created campaign act_123 with two ad sets.']],
            ]),
        ]);

        $result = (new AnthropicClient('sk-ant-test', 'claude-sonnet-5'))->runAgenticCampaignAction(
            systemPrompt: 'system',
            userPrompt: 'user',
            mcpUrl: 'https://mcp.example.com/meta-ads',
            mcpToken: 'mcp-token-abc',
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('act_123', $result['transcript']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('anthropic-beta', 'mcp-client-2025-04-04')
                && $request['mcp_servers'][0]['url'] === 'https://mcp.example.com/meta-ads'
                && $request['mcp_servers'][0]['authorization_token'] === 'mcp-token-abc';
        });
    }

    public function test_running_an_agentic_campaign_action_reports_failure_when_a_tool_call_errors(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'mcp_tool_result', 'is_error' => true, 'text' => 'Meta rejected the request: invalid ad account.'],
                ],
            ]),
        ]);

        $result = (new AnthropicClient('sk-ant-test', 'claude-sonnet-5'))->runAgenticCampaignAction(
            systemPrompt: 'system',
            userPrompt: 'user',
            mcpUrl: 'https://mcp.example.com/meta-ads',
            mcpToken: null,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid ad account', $result['transcript']);
    }

    public function test_running_an_agentic_campaign_action_reports_failure_when_the_request_itself_fails(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'model overloaded']], 529),
        ]);

        $result = (new AnthropicClient('sk-ant-test', 'claude-sonnet-5'))->runAgenticCampaignAction(
            systemPrompt: 'system',
            userPrompt: 'user',
            mcpUrl: 'https://mcp.example.com/meta-ads',
            mcpToken: null,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('model overloaded', $result['message']);
    }
}
