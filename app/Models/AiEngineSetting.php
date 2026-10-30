<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Admin choice of AI engine for text generation (Admin → AI Agents → AI
 * Engine): OpenAI, Claude or Gemini, tried in order when one fails.
 * Singleton row; every API key is stored encrypted here and falls back to
 * the server .env (OPENAI_API_KEY, ANTHROPIC_API_KEY, GEMINI_API_KEY).
 */
#[Fillable([
    'text_engine', 'second_engine', 'fallback_enabled', 'credentials',
    'gemini_verified_at', 'gemini_verification_status', 'gemini_verification_message',
    'anthropic_verified_at', 'anthropic_verification_status', 'anthropic_verification_message',
    'openai_verified_at', 'openai_verification_status', 'openai_verification_message',
])]
class AiEngineSetting extends Model
{
    public const ENGINE_OPENAI = 'openai';

    public const ENGINE_ANTHROPIC = 'anthropic';

    public const ENGINE_GEMINI = 'gemini';

    public const ENGINES = [self::ENGINE_OPENAI, self::ENGINE_ANTHROPIC, self::ENGINE_GEMINI];

    protected function casts(): array
    {
        return [
            'fallback_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'anthropic_verified_at' => 'datetime',
            'openai_verified_at' => 'datetime',
            'gemini_verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1, 'text_engine' => self::ENGINE_OPENAI, 'fallback_enabled' => true]);
    }

    /**
     * The saved settings, or an unsaved default when the table isn't there
     * yet (fresh install mid-migration, or a test without a database) — so
     * resolving the AI provider can never crash a request.
     */
    public static function currentOrDefault(): self
    {
        try {
            return static::current();
        } catch (Throwable) {
            return new static(['text_engine' => self::ENGINE_OPENAI, 'fallback_enabled' => true]);
        }
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function anthropicApiKey(): string
    {
        return (string) ($this->credential('anthropic_api_key')
            ?: config('ai.anthropic.api_key')
            ?: rescue(fn () => BrainAgentSetting::current()->credential('anthropic_api_key'), null, false));
    }

    public function anthropicModel(): string
    {
        return (string) ($this->credential('anthropic_model') ?: config('ai.anthropic.model'));
    }

    public function hasAnthropic(): bool
    {
        return $this->anthropicApiKey() !== '';
    }

    public function openaiApiKey(): string
    {
        return (string) ($this->credential('openai_api_key') ?: config('ai.openai.api_key'));
    }

    public function openaiTextModel(): string
    {
        return (string) ($this->credential('openai_text_model') ?: config('ai.openai.text_model'));
    }

    public function openaiImageModel(): string
    {
        return (string) ($this->credential('openai_image_model') ?: config('ai.openai.image_model'));
    }

    public function hasOpenAi(): bool
    {
        return $this->openaiApiKey() !== '';
    }

    public function geminiApiKey(): string
    {
        return (string) ($this->credential('gemini_api_key') ?: config('ai.gemini.api_key'));
    }

    public function geminiModel(): string
    {
        return (string) ($this->credential('gemini_model') ?: config('ai.gemini.model'));
    }

    public function hasGemini(): bool
    {
        return $this->geminiApiKey() !== '';
    }

    public function hasEngine(string $engine): bool
    {
        return match ($engine) {
            self::ENGINE_OPENAI => $this->hasOpenAi(),
            self::ENGINE_ANTHROPIC => $this->hasAnthropic(),
            self::ENGINE_GEMINI => $this->hasGemini(),
            default => false,
        };
    }

    /**
     * Every engine in the order to try them: the main one, the chosen second
     * one, then the rest — so if one fails the next takes over.
     *
     * @return array<int, string>
     */
    public function engineOrder(): array
    {
        $main = in_array($this->text_engine, self::ENGINES, true) ? $this->text_engine : self::ENGINE_OPENAI;
        $second = in_array($this->second_engine, self::ENGINES, true) && $this->second_engine !== $main ? $this->second_engine : null;

        return array_values(array_unique(array_filter([$main, $second, ...self::ENGINES])));
    }
}
