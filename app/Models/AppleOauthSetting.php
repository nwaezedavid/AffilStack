<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for "Continue with Apple" — see
 * Filament: Site > Apple Login and App\Services\Auth\AppleSignInService.
 * credentials holds the Services ID, Team ID, Key ID and the .p8 private
 * key, encrypted at rest like GoogleOauthSetting.
 */
#[Fillable(['is_enabled', 'credentials'])]
class AppleOauthSetting extends Model
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
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }
}
