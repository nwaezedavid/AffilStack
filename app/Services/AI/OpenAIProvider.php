<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to the OpenAI-compatible Chat Completions + Images API directly via
 * Laravel's HTTP client. No SDK dependency: swap AI_PROVIDER + this class if
 * you later want Anthropic, Gemini, or a local model — everything upstream
 * of this class only knows about the AIProvider interface.
 */
class OpenAIProvider implements AIProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $textModel,
        protected string $imageModel,
        protected int $timeout,
    ) {}

    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $response = $this->client()->post('/chat/completions', [
            'model' => $options['model'] ?? $this->textModel,
            'temperature' => $options['temperature'] ?? 0.7,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new AIGenerationException('AI text generation failed: '.$response->body());
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $response = $this->client()->post('/chat/completions', [
            'model' => $options['model'] ?? $this->textModel,
            'temperature' => $options['temperature'] ?? 0.6,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt.' Respond with a single valid JSON object only, no prose, no markdown fences.'],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new AIGenerationException('AI JSON generation failed: '.$response->body());
        }

        $raw = (string) data_get($response->json(), 'choices.0.message.content', '{}');

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new AIGenerationException('AI returned invalid JSON: '.$e->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        $response = $this->client()->post('/images/generations', [
            'model' => $options['model'] ?? $this->imageModel,
            'prompt' => $prompt,
            'size' => $options['size'] ?? '1024x1024',
            'n' => 1,
        ]);

        if ($response->failed()) {
            throw new AIGenerationException('AI image generation failed: '.$response->body());
        }

        return (string) data_get($response->json(), 'data.0.url', '');
    }

    protected function client()
    {
        return Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
