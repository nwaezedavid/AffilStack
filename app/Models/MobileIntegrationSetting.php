<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for the phone apps' push
 * notifications and app links — see Filament: Site > App Push & Links.
 * credentials holds the optional Expo access token (encrypted at rest);
 * the Apple Team ID and Android signing fingerprints are public values
 * that go into /.well-known/apple-app-site-association and
 * /.well-known/assetlinks.json.
 */
#[Fillable(['push_enabled', 'credentials', 'ios_team_id', 'android_sha256_fingerprints'])]
class MobileIntegrationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'android_sha256_fingerprints' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1, 'push_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * @return array<int, string>
     */
    public function androidFingerprints(): array
    {
        return array_values(array_filter((array) $this->android_sha256_fingerprints, 'is_string'));
    }
}
