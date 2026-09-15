<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected static array $previewOverrides = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$previewOverrides)) {
            return static::$previewOverrides[$key] ?? $default;
        }

        return Cache::rememberForever("site_setting:{$key}", function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("site_setting:{$key}");
    }

    /**
     * Lets a pending Tony (the Creative Agent) branding task preview
     * exactly how the real public templates would render with its
     * proposed values — every SiteSetting::get() call made inside
     * $callback sees $overrides instead of the stored ones. Nothing is
     * ever written to the database; the override is cleared in a finally
     * block even if rendering throws, so it can never leak into another
     * request. See CreativeTaskPreviewController.
     */
    public static function withPreviewOverrides(array $overrides, callable $callback): mixed
    {
        $previous = static::$previewOverrides;
        static::$previewOverrides = $overrides;

        try {
            return $callback();
        } finally {
            static::$previewOverrides = $previous;
        }
    }
}
