<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for "Continue with Google" — see
 * Filament: Site > Google Login and App\Services\Auth\GoogleOAuthService.
 * Credentials are encrypted at rest, same as PaymentGatewaySetting.
 */
#[Fillable(['is_enabled', 'credentials'])]
class GoogleOauthSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }
}
