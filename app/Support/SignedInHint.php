<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * "This browser is signed in to the app" — a hint for the public website
 * when the app lives on its own subdomain (see SiteDomains).
 *
 * Set on the parent domain (.affilstack.com) at login and cleared at
 * logout. It holds no credentials and grants nothing: a forged one only
 * shows a "Dashboard" button, which leads to the app's login page.
 */
class SignedInHint
{
    public const COOKIE = 'afs_signed_in';

    /** Minutes — matches the "remember me" window. */
    public const LIFETIME = 60 * 24 * 30;

    public static function present(Request $request): bool
    {
        return $request->cookies->get(self::COOKIE) === '1';
    }

    public static function set(): void
    {
        if (SiteDomains::isSplit()) {
            Cookie::queue(Cookie::make(self::COOKIE, '1', self::LIFETIME, '/', static::domain(), static::secure(), true, false, 'lax'));
        }
    }

    public static function clear(): void
    {
        if (SiteDomains::isSplit()) {
            Cookie::queue(Cookie::forget(self::COOKIE, '/', static::domain()));
        }
    }

    /**
     * ".affilstack.com" — shared by the website and every subdomain.
     */
    public static function domain(): string
    {
        return '.'.preg_replace('/^www\./', '', SiteDomains::marketingHost());
    }

    protected static function secure(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }
}
