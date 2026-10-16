<?php

namespace App\Support\Translation;

use App\Models\SiteLanguage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The languages the site is offered in. English is the source and has no
 * row; every other language is a SiteLanguage, "live" (in the switcher,
 * suggested to visitors, in hreflang) or "draft" (reachable by address for
 * admins to check, never advertised).
 */
class Languages
{
    public const SOURCE = 'en';

    public const COOKIE = 'afs_locale';

    public const DISMISSED_COOKIE = 'afs_locale_stay';

    protected const CACHE_KEY = 'site_languages:v1';

    /** Kept for one request (or one queued job), so changes in admin show up everywhere. */
    protected const INSTANCE = 'translation.languages';

    /**
     * Every language except English, keyed by code, in display order.
     *
     * @return array<string, array{code: string, name: string, native_name: string, status: string, rtl: bool}>
     */
    public static function all(): array
    {
        $app = app();

        if (! $app->bound(self::INSTANCE)) {
            $app->scoped(self::INSTANCE, fn () => static::load());
        }

        return $app->make(self::INSTANCE);
    }

    /**
     * @return array<string, array{code: string, name: string, native_name: string, status: string, rtl: bool}>
     */
    protected static function load(): array
    {
        try {
            $languages = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('site_languages')) {
                    return [];
                }

                return SiteLanguage::query()->orderBy('sort_order')->orderBy('name')->get()
                    ->mapWithKeys(fn (SiteLanguage $language) => [$language->code => [
                        'code' => $language->code,
                        'name' => $language->name,
                        'native_name' => $language->native_name,
                        'status' => $language->status,
                        'rtl' => (bool) $language->rtl,
                    ]])->all();
            });
        } catch (Throwable) {
            $languages = [];
        }

        return is_array($languages) ? $languages : [];
    }

    /**
     * @return array<int, string>
     */
    public static function liveCodes(): array
    {
        return array_keys(array_filter(static::all(), fn ($language) => $language['status'] === 'live'));
    }

    public static function exists(string $code): bool
    {
        return isset(static::all()[$code]);
    }

    public static function isLive(string $code): bool
    {
        return (static::all()[$code]['status'] ?? null) === 'live';
    }

    /**
     * English or a live language: what visitors may choose.
     */
    public static function selectable(?string $code): bool
    {
        return $code === self::SOURCE || ($code !== null && static::isLive($code));
    }

    public static function nativeName(string $code): string
    {
        return $code === self::SOURCE ? 'English' : (static::all()[$code]['native_name'] ?? strtoupper($code));
    }

    public static function name(string $code): string
    {
        return $code === self::SOURCE ? 'English' : (static::all()[$code]['name'] ?? strtoupper($code));
    }

    public static function isRtl(string $code): bool
    {
        return (bool) (static::all()[$code]['rtl'] ?? false);
    }

    /**
     * English plus the live languages, for switchers.
     *
     * @return array<string, string> code => native name
     */
    public static function switcherOptions(): array
    {
        $options = [self::SOURCE => 'English'];

        foreach (static::liveCodes() as $code) {
            $options[$code] = static::nativeName($code);
        }

        return $options;
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::INSTANCE);
        Cache::forget(self::CACHE_KEY);
    }
}
