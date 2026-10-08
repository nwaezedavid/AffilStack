<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Admin choice of AI engine for text generation (Admin → AI Agents → AI
 * Engine). Singleton row; the Claude API key is stored encrypted here and
 * falls back to ANTHROPIC_API_KEY in .env, then to the key saved for Brain. OpenAI's key stays in .env.
 */
#[Fillable([
    'text_engine', 'fallback_enabled', 'credentials',
    'anthropic_verified_at', 'anthropic_verification_status', 'anthropic_verification_message',
    'openai_verified_at', 'openai_verification_status', 'openai_verification_message',
])]
class AiEngineSetting extends Model
{
    public const ENGINE_OPENAI = 'openai';

    public const ENGINE_ANTHROPIC = 'anthropic';

    protected function casts(): array
    {
        return [
            'fallback_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'anthropic_verified_at' => 'datetime',
            'openai_verified_at' => 'datetime',
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

    public function hasOpenAi(): bool
    {
        return filled(config('ai.openai.api_key'));
    }
}
