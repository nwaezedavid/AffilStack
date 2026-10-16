<?php

namespace App\Support\Translation;

use App\Support\SiteDomains;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Addresses of the public website in each language: the English address
 * with the language code in front (affilstack.com/fr/pricing). Only pages
 * of the public website (routes registered with SiteDomains::marketing,
 * which marks them "localized-url") have language addresses; the customer
 * dashboard and partner portal follow the visitor's chosen language.
 */
class LocalizedUrls
{
    public const MIDDLEWARE = 'localized-url';

    /** @var array<string, bool> */
    protected static array $matches = [];

    public static function routeIsLocalized(?Route $route): bool
    {
        return $route !== null && in_array(self::MIDDLEWARE, $route->gatherMiddleware(), true);
    }

    /**
     * Whether an address (absolute or a path) is a public website page.
     */
    public static function isLocalizedPage(string $url, string $method = 'GET'): bool
    {
        $parts = parse_url($url);

        if ($parts === false || isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host !== '' && $host !== strtolower(SiteDomains::marketingHost())) {
            return false;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        [$prefix] = static::split($path);

        if ($prefix !== null) {
            return false; // already in a language
        }

        $key = $method.' '.$path;

        if (array_key_exists($key, static::$matches)) {
            return static::$matches[$key];
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create(SiteDomains::publicUrl($path), $method));
            $result = static::routeIsLocalized($route);
        } catch (HttpException|Throwable) {
            $result = false;
        }

        if (count(static::$matches) > 2000) {
            static::$matches = [];
        }

        return static::$matches[$key] = $result;
    }

    /**
     * An address in a language (English: no prefix). Leaves anything that
     * isn't a public website page alone.
     */
    public static function to(string $url, string $locale, string $method = 'GET'): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return null;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        [, $bare] = static::split($path);

        if (! static::isLocalizedPage(static::rebuild($parts, $bare), $method)) {
            return null;
        }

        $localizedPath = $locale === Languages::SOURCE ? $bare : '/'.$locale.($bare === '/' ? '' : $bare);

        return static::rebuild($parts, $localizedPath);
    }

    /**
     * The page being viewed, in another language.
     */
    public static function current(Request $request, string $locale): string
    {
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $localizedPath = $locale === Languages::SOURCE ? $path : '/'.$locale.($path === '/' ? '' : $path);

        return rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/').$localizedPath;
    }

    /**
     * @return array{0: ?string, 1: string} language prefix (if any), path without it
     */
    public static function split(string $path): array
    {
        if (preg_match('#^/([a-z]{2}(?:-[a-z0-9]{2,4})?)(/.*)?$#i', $path, $match) && Languages::exists(strtolower($match[1]))) {
            return [strtolower($match[1]), $match[2] ?? '/'];
        }

        return [null, $path];
    }

    /**
     * <link rel="alternate" hreflang> tags for the page being viewed.
     */
    public static function alternates(Request $request): string
    {
        $tags = '';

        foreach (array_merge([Languages::SOURCE], Languages::liveCodes()) as $code) {
            $tags .= '<link rel="alternate" hreflang="'.e($code).'" href="'.e(static::current($request, $code)).'">';
        }

        return $tags.'<link rel="alternate" hreflang="x-default" href="'.e(static::current($request, Languages::SOURCE)).'">';
    }

    /**
     * A website page's address in English and every live language (for the
     * sitemap); empty for anything that isn't a website page.
     *
     * @return array<string, string>
     */
    public static function alternatesFor(string $url): array
    {
        if (Languages::liveCodes() === [] || static::to($url, Languages::SOURCE) === null) {
            return [];
        }

        $alternates = [];

        foreach (array_merge([Languages::SOURCE], Languages::liveCodes()) as $code) {
            $alternates[$code] = (string) static::to($url, $code);
        }

        return $alternates;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    protected static function rebuild(array $parts, string $path): string
    {
        $prefix = isset($parts['host']) ? ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';

        return $prefix.$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public static function flush(): void
    {
        static::$matches = [];
    }
}
