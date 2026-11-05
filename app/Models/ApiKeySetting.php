<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single-row admin settings for the keys and links that used to live only
 * in the server .env — see Filament: Site > API keys & links. Values saved
 * here override the .env ones at boot (applyToConfig()); a blank field
 * leaves the .env value in place.
 */
#[Fillable(['credentials', 'chrome_extension_store_url', 'zapier_invite_url'])]
class ApiKeySetting extends Model
{
    protected const CACHE_KEY = 'api_key_settings:config';

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * @return array<string, string>
     */
    public function toConfig(): array
    {
        return array_filter([
            'services.google_places.api_key' => (string) $this->credential('google_places_api_key', ''),
            'extension.store_url' => (string) $this->chrome_extension_store_url,
            'api_hub.zapier_invite_url' => (string) $this->zapier_invite_url,
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * Applied from AppServiceProvider::boot(). The values are cached
     * encrypted, so a key never sits in the cache store in plain text.
     */
    public static function applyToConfig(): void
    {
        try {
            $sealed = Cache::rememberForever(self::CACHE_KEY, function (): string {
                $config = Schema::hasTable('api_key_settings') ? static::current()->toConfig() : [];

                return Crypt::encryptString(json_encode($config));
            });

            $config = json_decode(Crypt::decryptString($sealed), true);
        } catch (Throwable) {
            return;
        }

        if (is_array($config) && $config !== []) {
            config($config);
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
