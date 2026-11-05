<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What "Continue with Google" and "Continue with Apple" share once the
 * provider has proven who the person is: linking the provider to an
 * existing account, and finishing the sign-in (two-factor challenge
 * included). Kept in one place so both buttons follow the same security
 * rules.
 */
class SocialSignIn
{
    /**
     * Links $providerId (users.$column) to $user. The first time a
     * provider is linked to an account created with a password, the
     * provider has just proven ownership of the email that signup never
     * did — so the old password, two-factor setup, other sessions, API
     * tokens and passkeys are retired, and a pre-registered account can't
     * stay shared with an intruder. Returns whether that happened.
     *
     * @param  array<string, mixed>  $extra  more columns to save with the link
     */
    public function link(User $user, string $column, string $providerId, array $extra = []): bool
    {
        if (filled($user->{$column})) {
            if ($extra !== []) {
                $user->forceFill($extra)->save();
            }

            return false;
        }

        $user->forceFill([
            $column => $providerId,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(60),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            ...$extra,
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->apiTokens()->delete();

        if (method_exists($user, 'passkeys')) {
            $user->passkeys()->delete();
        }

        return true;
    }

    /**
     * Signs $user in — or, when two-factor sign-in is on, hands over to the
     * challenge (the provider proves who the person is, not the second
     * factor) — and returns them to the page that asked for sign-in (e.g.
     * the phone app's sign-in page), or the dashboard.
     */
    public function complete(Request $request, User $user, string $message): RedirectResponse
    {
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login')->with('success', $message);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', $message);
    }
}
