<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Claude (Anthropic Messages API) behind the app-wide AIProvider contract,
 * so every content module can run on Claude without changing. Claude
 * doesn't generate images, so generateImage() is never routed here — see
 * RoutingAIProvider.
 */
class AnthropicProvider implements AIProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected string $baseUrl = 'https://api.anthropic.com',
        protected string $version = '2023-06-01',
        protected int $maxTokens = 8000,
        protected int $timeout = 120,
    ) {}

    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->message($systemPrompt, $userPrompt, $options);
    }

    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $raw = $this->message(
            $systemPrompt.' Respond with a single valid JSON object only — no prose, no markdown code fences.',
            $userPrompt,
            $options,
        );

        $raw = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw)));

        // Claude occasionally adds a sentence around the object; keep only
        // the outermost {...} rather than failing the whole generation.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw AIGenerationException::fromProvider('Claude returned invalid JSON', ['error' => $e->getMessage()], AIGenerationException::BAD_ANSWER, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        throw new AIGenerationException('Claude does not generate images; image requests use OpenAI.');
    }

    /**
     * A cheap authenticated call (list models) to confirm the key works.
     *
     * @return array{success: bool, message: string}
     */
    public function verify(): array
    {
        try {
            $response = $this->client()->get('/v1/models', ['limit' => 1]);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Anthropic: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Claude API key works. Text will be written with '.$this->model.'.'];
        }

        $error = (string) (data_get($response->json(), 'error.message') ?: $response->body());

        return ['success' => false, 'message' => $response->status() === 401
            ? 'Anthropic rejected this API key. Create a new key at console.anthropic.com → API Keys.'
            : 'Anthropic returned an error: '.$error];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function message(string $systemPrompt, string $userPrompt, array $options): string
    {
        // Callers pass OpenAI model names in $options['model'] (e.g. the
        // per-module overrides); only honour a Claude model name here.
        $model = isset($options['model']) && str_starts_with((string) $options['model'], 'claude-')
            ? (string) $options['model']
            : $this->model;

        try {
            $response = $this->client()->post('/v1/messages', array_filter([
                'model' => $model,
                'max_tokens' => (int) ($options['max_tokens'] ?? $this->maxTokens),
                'temperature' => $options['temperature'] ?? null,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ], fn ($value) => $value !== null));
        } catch (ConnectionException $e) {
            throw AIGenerationException::fromProvider('Claude could not be reached', ['error' => $e->getMessage()], previous: $e);
        }

        if ($response->failed()) {
            throw AIGenerationException::fromProvider('Claude generation failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);
        }

        return collect((array) data_get($response->json(), 'content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
        ])
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
