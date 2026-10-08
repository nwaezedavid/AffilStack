<?php

namespace App\Services\AI;

use App\Models\AiEngineSetting;
use Illuminate\Support\Facades\Log;

/**
 * The AIProvider every module receives. Sends text and JSON work to the
 * engine chosen in Admin → AI Agents → AI Engine (OpenAI or Claude) and,
 * when fallback is on and the other engine is configured, retries there if
 * the first one fails — so a provider outage doesn't stop customers'
 * generations. Images always go to OpenAI (Claude can't make images).
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
        $available = [];

        if ($settings->hasOpenAi()) {
            $available[AiEngineSetting::ENGINE_OPENAI] = $this->openai;
        }

        if ($settings->hasAnthropic()) {
            $available[AiEngineSetting::ENGINE_ANTHROPIC] = static::anthropicFor($settings);
        }

        $primary = $settings->text_engine === AiEngineSetting::ENGINE_ANTHROPIC
            ? AiEngineSetting::ENGINE_ANTHROPIC
            : AiEngineSetting::ENGINE_OPENAI;

        // Nothing configured at all: keep the long-standing behaviour
        // (OpenAI, which then reports its own missing-key error).
        if ($available === []) {
            return [AiEngineSetting::ENGINE_OPENAI => $this->openai];
        }

        $ordered = [];

        if (isset($available[$primary])) {
            $ordered[$primary] = $available[$primary];
        }

        if ($ordered === [] || $settings->fallback_enabled) {
            $ordered += $available;
        }

        return $ordered;
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
