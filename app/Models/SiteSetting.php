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
     * Convenience for a boolean-style setting. The underlying store is a
     * plain string column (see the migration), so a Filament Toggle's
     * boolean state must be written as '1'/'0' rather than a real bool —
     * see setFlag(). Reading tolerates either shape (a real bool from a
     * Tony preview override, or the '1'/'0' string this normally reads
     * back as) since (bool) casts both correctly ('0' is falsy, '1' and
     * true are truthy).
     */
    public static function flag(string $key, bool $default = true): bool
    {
        return (bool) static::get($key, $default ? '1' : '0');
    }

    public static function setFlag(string $key, bool $value): void
    {
        static::set($key, $value ? '1' : '0');
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
