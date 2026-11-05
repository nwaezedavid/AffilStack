<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini as a text engine, through the Gemini API's generateContent
 * endpoint (an API key from Google AI Studio). Images stay with OpenAI.
 */
class GeminiProvider implements AIProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        protected int $timeout = 120,
    ) {}

    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->generate($systemPrompt, $userPrompt, $options, json: false);
    }

    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $raw = $this->generate($systemPrompt.' Respond with a single valid JSON object only, no prose, no markdown fences.', $userPrompt, $options, json: true);
        $raw = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw AIGenerationException::fromProvider('Gemini returned invalid JSON', ['error' => $e->getMessage()], AIGenerationException::BAD_ANSWER, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        throw AIGenerationException::fromProvider('Gemini is used for text only; images are made with OpenAI');
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verify(): array
    {
        try {
            $response = $this->client()->get('/models/'.$this->model);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Google: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Gemini API key works. Text will be written with '.$this->model.'.'];
        }

        $error = (string) (data_get($response->json(), 'error.message') ?: $response->body());

        return ['success' => false, 'message' => match ($response->status()) {
            400, 401, 403 => 'Google rejected this API key: '.mb_substr($error, 0, 200).' Create a key at aistudio.google.com → Get API key.',
            404 => "The model {$this->model} isn't available to this key. Pick another Gemini model.",
            default => 'Google returned an error: '.mb_substr($error, 0, 200),
        }];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function generate(string $systemPrompt, string $userPrompt, array $options, bool $json): string
    {
        // Callers may pass OpenAI model names in $options['model']; only a Gemini name is honoured.
        $model = is_string($options['model'] ?? null) && str_starts_with($options['model'], 'gemini') ? $options['model'] : $this->model;

        $config = ['temperature' => $options['temperature'] ?? ($json ? 0.6 : 0.7)];

        if ($json) {
            $config['responseMimeType'] = 'application/json';
        }

        $response = $this->post('/models/'.$model.':generateContent', [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
            'generationConfig' => $config,
        ]);

        if ($response->failed()) {
            throw AIGenerationException::fromProvider('Gemini generation failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);
        }

        $text = collect(data_get($response->json(), 'candidates.0.content.parts', []))
            ->map(fn ($part) => is_array($part) && empty($part['thought']) ? (string) ($part['text'] ?? '') : '')
            ->implode('');

        if ($text === '') {
            throw AIGenerationException::fromProvider('Gemini returned no text', ['finish_reason' => data_get($response->json(), 'candidates.0.finishReason'), 'block_reason' => data_get($response->json(), 'promptFeedback.blockReason')], AIGenerationException::BAD_ANSWER);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $path, array $payload): Response
    {
        try {
            return $this->client()->post($path, $payload);
        } catch (ConnectionException $e) {
            throw AIGenerationException::fromProvider('Gemini could not be reached', ['error' => $e->getMessage()], previous: $e);
        }
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
