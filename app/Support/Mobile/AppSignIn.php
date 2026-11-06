<?php

namespace App\Support\Mobile;

/**
 * "This sign-in started in the AffilStack phone app." Set when a signed-out
 * visitor opens /mobile/authorize and is sent to the sign-in page, so the
 * sign-in pages shown inside the app (password, two-factor, forgot password,
 * Google) leave out every link to plans, pricing or sign-up, and Google
 * sign-in for an unknown email returns to the sign-in page instead of the
 * pricing page. Apple and Google don't allow apps to point people to buying
 * outside the app (App Store guideline 3.1.1 / 3.1.3; Google Play's Payments
 * policy for consumption-only apps). It lapses on its own after a while, or
 * as soon as the app is handed its sign-in code.
 */
class AppSignIn
{
    public const SESSION_KEY = 'mobile_app_sign_in_started_at';

    public const MINUTES = 30;

    public static function start(): void
    {
        session()->put(self::SESSION_KEY, now()->getTimestamp());
    }

    public static function active(): bool
    {
        $startedAt = session()->get(self::SESSION_KEY);

        return is_int($startedAt) && $startedAt >= now()->subMinutes(self::MINUTES)->getTimestamp();
    }

    public static function finish(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
