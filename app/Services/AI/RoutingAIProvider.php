<?php

namespace App\Services\AI;

use App\Models\AiEngineSetting;
use Illuminate\Support\Facades\Log;

/**
 * The AIProvider every module receives. Sends text and JSON work to the
 * engine chosen in Admin → AI Agents → AI Engine (OpenAI, Claude or
 * Gemini) and, when fallback is on, moves on to the next configured engine
 * in the chosen order whenever one fails — so a provider outage or an empty
 * account doesn't stop customers' generations. Images always go to OpenAI.
 */
class RoutingAIProvider implements AIProvider
{
    public function __construct(
        protected OpenAIProvider $openai,
    ) {}

    public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->attempt(fn (AIProvider $engine) => $engine->generateText($systemPrompt, $userPrompt, $options));
    }

    public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        return $this->attempt(fn (AIProvider $engine) => $engine->generateJson($systemPrompt, $userPrompt, $options));
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        return $this->openai->generateImage($prompt, $options);
    }

    /**
     * Engines to try for text, in order.
     *
     * @return array<string, AIProvider>
     */
    public function textEngines(): array
    {
        $settings = AiEngineSetting::currentOrDefault();
        $ordered = [];

        foreach ($settings->engineOrder() as $engine) {
            if ($settings->hasEngine($engine)) {
                $ordered[$engine] = $this->engine($engine, $settings);
            }
        }

        // Nothing configured at all: keep the long-standing behaviour
        // (OpenAI, which then reports its own missing-key error).
        if ($ordered === []) {
            return [AiEngineSetting::ENGINE_OPENAI => $this->openai];
        }

        // Fallback off: only the main engine (or, if it isn't set up, the first that is).
        return $settings->fallback_enabled ? $ordered : array_slice($ordered, 0, 1, true);
    }

    protected function engine(string $engine, AiEngineSetting $settings): AIProvider
    {
        return match ($engine) {
            AiEngineSetting::ENGINE_ANTHROPIC => static::anthropicFor($settings),
            AiEngineSetting::ENGINE_GEMINI => static::geminiFor($settings),
            default => $this->openai,
        };
    }

    public static function geminiFor(AiEngineSetting $settings): GeminiProvider
    {
        return new GeminiProvider(
            apiKey: $settings->geminiApiKey(),
            model: $settings->geminiModel(),
            baseUrl: (string) config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'),
            timeout: (int) config('ai.gemini.timeout', 120),
        );
    }

    public static function anthropicFor(AiEngineSetting $settings): AnthropicProvider
    {
        return new AnthropicProvider(
            apiKey: $settings->anthropicApiKey(),
            model: $settings->anthropicModel(),
            baseUrl: (string) config('ai.anthropic.base_url', 'https://api.anthropic.com'),
            version: (string) config('ai.anthropic.version', '2023-06-01'),
            maxTokens: (int) config('ai.anthropic.max_tokens', 8000),
            timeout: (int) config('ai.anthropic.timeout', 120),
        );
    }

    /**
     * @template T
     *
     * @param  callable(AIProvider): T  $call
     * @return T
     */
    protected function attempt(callable $call): mixed
    {
        $lastError = null;

        foreach ($this->textEngines() as $name => $engine) {
            try {
                return $call($engine);
            } catch (AIGenerationException $e) {
                $lastError = $e;
                Log::warning("AI engine [{$name}] failed; trying the next one if any.", ['error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        }

        throw $lastError ?? new AIGenerationException('No AI engine is configured.');
    }
}
