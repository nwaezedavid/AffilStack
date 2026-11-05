<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\AppleSignInService;
use App\Services\Auth\SocialSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Continue with Apple" on the website sign-in page — which is also the
 * sign-in page the AffilStack phone app opens, so iPhone users get the
 * Apple option Apple requires alongside Google (guideline 4.8).
 *
 * Sign-in only: like Google from the login page, it never creates an
 * account (accounts start with a paid plan). An Apple ID whose verified
 * email matches an AffilStack account is linked to it; an Apple "Hide My
 * Email" address that matches nothing gets a clear message instead of a
 * sales page (the phone app may be showing this page).
 *
 * Apple sends its answer as a cross-site POST, which doesn't carry the
 * visitor's session cookie. So the POST (handled without the session
 * middleware, so it can't replace that cookie) only parks Apple's answer
 * for a minute and bounces the browser to finish(), a normal same-site
 * GET where the session — with the state and nonce from redirect() and
 * the page that asked for sign-in — is available again.
 */
class AppleAuthController extends Controller
{
    protected const SESSION_STATE_KEY = 'apple_oauth_state';

    protected const SESSION_NONCE_KEY = 'apple_oauth_nonce';

    protected const CACHE_PREFIX = 'apple-signin:';

    public function redirect(Request $request, AppleSignInService $apple): RedirectResponse
    {
        if (! $apple->isEnabled()) {
            return redirect()->route('login')->with('error', 'Sign in with Apple is not available right now.');
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $request->session()->put([self::SESSION_STATE_KEY => $state, self::SESSION_NONCE_KEY => $nonce]);

        return redirect()->away($apple->authorizationUrl(route('apple.callback'), $state, $nonce));
    }

    /**
     * Apple's form_post. No session, no CSRF token (Apple can't send one);
     * nothing is trusted here — finish() checks the state against the
     * visitor's own session.
     */
    public function callback(Request $request): RedirectResponse
    {
        $key = Str::random(48);

        Cache::put(self::CACHE_PREFIX.$key, [
            'state' => mb_substr((string) $request->input('state', ''), 0, 200),
            'code' => mb_substr((string) $request->input('code', ''), 0, 2000),
            'error' => mb_substr((string) $request->input('error', ''), 0, 100),
        ], now()->addMinutes(2));

        return redirect()->route('apple.finish', ['key' => $key], 303);
    }

    public function finish(Request $request, AppleSignInService $apple, SocialSignIn $socialSignIn): RedirectResponse
    {
        $payload = Cache::pull(self::CACHE_PREFIX.mb_substr((string) $request->query('key', ''), 0, 64));
        $state = $request->session()->pull(self::SESSION_STATE_KEY);
        $nonce = $request->session()->pull(self::SESSION_NONCE_KEY);

        if (is_array($payload) && $payload['error'] === 'user_cancelled_authorize') {
            return redirect()->route('login');
        }

        if (! is_array($payload) || ! $state || ! $nonce || ! hash_equals($state, $payload['state']) || $payload['code'] === '') {
            return redirect()->route('login')->with('error', 'Apple sign-in failed — please try again.');
        }

        try {
            $profile = $apple->resolveProfile($payload['code'], route('apple.callback'), $nonce);
        } catch (\RuntimeException $e) {
            report($e);

            return redirect()->route('login')->with('error', 'We could not complete Apple sign-in. Please try again.');
        }

        $user = User::where('apple_id', $profile['sub'])->first();

        if (! $user && $profile['email'] !== '' && $profile['email_verified']) {
            $user = User::where('email', $profile['email'])->first();

            if ($user && $user->apple_id && ! hash_equals((string) $user->apple_id, $profile['sub'])) {
                return redirect()->route('login')->with('error', 'This account is linked to a different Apple ID. Sign in with that Apple ID or with your password.');
            }
        }

        if (! $user) {
            return redirect()->route('login')->with('error', $profile['is_private_email']
                ? 'Your Apple ID hides your email, so we could not find your AffilStack account. Sign in with your email and password (or Google) instead.'
                : 'No AffilStack account uses the email on this Apple ID. Sign in with your email and password (or Google) instead.');
        }

        $retired = $socialSignIn->link($user, 'apple_id', $profile['sub'], array_filter(['apple_refresh_token' => $profile['refresh_token']]));

        $message = $retired
            ? 'Signed in with Apple. For your security your previous password was retired — keep using Apple, or use "Forgot password" to set a new one.'
            : 'Signed in with Apple.';

        return $socialSignIn->complete($request, $user, $message);
    }
}
