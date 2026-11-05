<?php

namespace App\Models;

use App\Services\Video\VideoStyles;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row admin settings for one-click videos — see Filament: Content >
 * UGC Video Settings. HeyGen keeps its own row (HeyGenSetting); this holds
 * Higgsfield (the second engine) and the styles customers pick from: each
 * style's on/off switch, credit price and length.
 */
#[Fillable([
    'higgsfield_enabled', 'credentials', 'higgsfield_model', 'higgsfield_resolution',
    'higgsfield_verified_at', 'higgsfield_verification_status', 'higgsfield_verification_message',
    'styles', 'heygen_default_avatar_id', 'heygen_default_voice_id',
])]
class VideoStudioSetting extends Model
{
    protected function casts(): array
    {
        return [
            'higgsfield_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'higgsfield_verified_at' => 'datetime',
            'styles' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1, 'higgsfield_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function higgsfieldKeyId(): ?string
    {
        return $this->credential('key_id') ?: null;
    }

    public function higgsfieldKeySecret(): ?string
    {
        return $this->credential('key_secret') ?: null;
    }

    public function higgsfieldWebhookSecret(): ?string
    {
        return $this->credential('webhook_secret') ?: null;
    }

    public function higgsfieldReady(): bool
    {
        return $this->higgsfield_enabled
            && $this->higgsfieldKeyId() !== null
            && $this->higgsfieldKeySecret() !== null
            && $this->higgsfield_verification_status === 'success';
    }

    /**
     * One style's settings merged over its defaults.
     *
     * @return array{enabled: bool, credits: int, duration: int}
     */
    public function style(string $key): array
    {
        $defaults = VideoStyles::get($key);
        $saved = is_array($this->styles[$key] ?? null) ? $this->styles[$key] : [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'credits' => max(1, (int) ($saved['credits'] ?? $defaults['credits'])),
            'duration' => (int) ($saved['duration'] ?? $defaults['duration']),
        ];
    }
}
