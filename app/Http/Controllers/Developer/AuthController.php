<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SiteDomains;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Sign in with your AffilStack account" for the developer portal.
 *
 * On its own host (developer.affilstack.com) the portal has its own
 * session, so it sends the developer to the app, which — once they're
 * signed in there, two-factor and all — hands back a one-time token
 * that's good for a minute. Without its own host the portal shares the
 * app's session and simply uses the app's login page.
 */
class AuthController extends Controller
{
    protected const CACHE_PREFIX = 'developer-sso:';

    public function start(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->intended(route('developer.dashboard'));
        }

        if (SiteDomains::developerHost() === null) {
            return redirect()->route('login');
        }

        $state = Str::random(40);
        $request->session()->put('developer_sso_state', $state);

        return redirect()->away(route('developer.sso', ['state' => $state]));
    }

    /**
     * On the app: vouch for the signed-in customer.
     */
    public function vouch(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        if (SiteDomains::developerHost() === null || ! preg_match('/^[A-Za-z0-9]{20,64}$/', $state)) {
            return redirect()->route('dashboard');
        }

        $token = Str::random(64);
        Cache::put(self::CACHE_PREFIX.hash('sha256', $token), ['user_id' => $request->user()->id, 'state' => $state], 60);

        return redirect()->away(route('developer.sso.callback', ['token' => $token, 'state' => $state]));
    }

    /**
     * On the portal: sign in with the app's token.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('developer_sso_state');
        $data = Cache::pull(self::CACHE_PREFIX.hash('sha256', (string) $request->query('token', '')));

        if (! is_string($expectedState) || ! is_array($data) || ! hash_equals($expectedState, (string) $request->query('state', '')) || ! hash_equals($data['state'] ?? '', $expectedState)) {
            return redirect()->route('developer.home')->with('error', 'That sign-in link has expired. Please sign in again.');
        }

        $user = User::find($data['user_id'] ?? null);

        if (! $user || $user->is_suspended) {
            return redirect()->route('developer.home')->with('error', 'We could not sign you in.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('developer.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if (SiteDomains::developerHost() === null) {
            return redirect()->route('developer.home');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('developer.home');
    }
}
