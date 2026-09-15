<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talks to Anthropic's Messages API directly for Brain (the Marketing
 * Agent) — deliberately separate from the app-wide AIProvider interface
 * (bound to OpenAI in AppServiceProvider and used by every other content
 * module), since the user specifically wants Brain's copywriting to run on
 * their own Claude/Anthropic account rather than the shared OpenAI key.
 * Credentials come from BrainAgentSetting, entered in the admin dashboard —
 * never from .env — so every instance is built fresh per call rather than
 * bound as a long-lived singleton (same reasoning as GoogleOAuthService).
 *
 * runAgenticCampaignAction() additionally attaches a remote MCP server via
 * the Messages API's MCP connector (the `mcp_servers` parameter, beta
 * header `mcp-client-2025-04-04`): Anthropic's own infrastructure calls the
 * MCP server's tools on Claude's behalf as part of that single request, so
 * no client-side tool-execution loop is needed here for the Meta Ads MCP
 * actions themselves. This is a beta surface — if Anthropic changes the
 * exact request/response shape, this is the one class that needs updating.
 */
class AnthropicClient
{
    protected const BASE_URL = 'https://api.anthropic.com';

    protected const API_VERSION = '2023-06-01';

    protected const MCP_BETA = 'mcp-client-2025-04-04';

    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected int $timeout = 60,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $response = $this->client()->post('/v1/messages', [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system' => $systemPrompt.' Respond with a single valid JSON object only — no prose, no markdown code fences.',
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new AIGenerationException('Anthropic JSON generation failed: '.$response->body());
        }

        $raw = $this->extractText($response->json());
        $raw = trim(preg_replace('/^```(json)?|```$/m', '', $raw));

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new AIGenerationException('Anthropic returned invalid JSON: '.$e->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyApiKey(): array
    {
        try {
            $response = $this->client()->get('/v1/models');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Anthropic: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Anthropic API key is valid.'];
        }

        return ['success' => false, 'message' => 'Anthropic rejected this key: '.$this->errorMessage($response)];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyMcpConnection(string $mcpUrl, ?string $mcpToken): array
    {
        $result = $this->runAgenticCampaignAction(
            systemPrompt: 'You are checking a Meta Ads MCP connection. Do not take any action.',
            userPrompt: 'In one short sentence, confirm you can see the connected MCP server and list the names of the tools it offers.',
            mcpUrl: $mcpUrl,
            mcpToken: $mcpToken,
        );

        return ['success' => $result['success'], 'message' => $result['message']];
    }

    /**
     * The actual "connect Brain to Meta Ads" call: attaches the Meta Ads
     * MCP server to a Messages API request so Claude can use its tools
     * directly. Used both to launch a newly approved campaign and, later,
     * by BrainAgentService::optimizeRunningCampaigns() to review and adjust
     * one that's already live.
     *
     * @return array{success: bool, message: string, transcript: string}
     */
    public function runAgenticCampaignAction(
        string $systemPrompt,
        string $userPrompt,
        string $mcpUrl,
        ?string $mcpToken,
        string $mcpServerName = 'meta_ads',
    ): array {
        try {
            $response = $this->client()
                ->withHeaders(['anthropic-beta' => self::MCP_BETA])
                ->post('/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'mcp_servers' => [array_filter([
                        'type' => 'url',
                        'name' => $mcpServerName,
                        'url' => $mcpUrl,
                        'authorization_token' => $mcpToken,
                    ], fn ($v) => $v !== null)],
                ]);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Anthropic/the MCP server: '.$e->getMessage(), 'transcript' => ''];
        }

        if ($response->failed()) {
            return ['success' => false, 'message' => $this->errorMessage($response), 'transcript' => $response->body()];
        }

        $body = $response->json();
        $blocks = (array) data_get($body, 'content', []);
        $transcript = collect($blocks)->pluck('text')->filter()->implode("\n\n");
        $hasToolError = collect($blocks)->contains(fn ($block) => data_get($block, 'is_error') === true);

        if ($hasToolError) {
            return ['success' => false, 'message' => 'The MCP server reported an error — see the transcript for details.', 'transcript' => $transcript ?: $response->body()];
        }

        return [
            'success' => true,
            'message' => Str::limit($transcript ?: 'Completed with no text response.', 200),
            'transcript' => $transcript ?: $response->body(),
        ];
    }

    protected function extractText(array $body): string
    {
        return (string) data_get($body, 'content.0.text', '');
    }

    protected function errorMessage($response): string
    {
        return (string) (data_get($response->json(), 'error.message') ?: $response->body());
    }

    protected function client()
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ])
            ->baseUrl(self::BASE_URL)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
