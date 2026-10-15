<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Fortify;

/**
 * Partner sign-in for the Partner Portal (affiliate.affilstack.com).
 * Its own session on its own host; only affiliate-only partner accounts
 * get in. A partner who turned on authenticator-app two-factor confirms a
 * code (or a recovery code) before the session starts.
 */
class AuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAffiliateOnly()) {
            return redirect()->route('partner.dashboard');
        }

        return view('partner.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Those details don\'t match a partner account.']);
        }

        if (! $user->isAffiliateOnly()) {
            throw ValidationException::withMessages(['email' => 'This is a customer account. Your referral link and earnings are in your AffilStack dashboard, under Refer & Earn.']);
        }

        if ($user->is_suspended) {
            throw ValidationException::withMessages(['email' => 'This partner account has been suspended. Contact support if you believe this is a mistake.']);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('partner.login.id', $user->id);
            $request->session()->put('partner.login.remember', $request->boolean('remember'));

            return redirect()->route('partner.two-factor');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('partner.dashboard'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('partner.login');
        }

        return view('partner.auth.two-factor');
    }

    public function verify(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('partner.login')->with('error', 'Please sign in again.');
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:50'],
        ]);

        $code = preg_replace('/\s+/', '', (string) $request->input('code'));
        $recovery = trim((string) $request->input('recovery_code'));

        if ($recovery !== '') {
            $match = collect($user->recoveryCodes())->first(fn (string $stored) => hash_equals($stored, $recovery));

            if (! $match) {
                throw ValidationException::withMessages(['recovery_code' => 'That recovery code isn\'t valid.']);
            }

            $user->replaceRecoveryCode($match);
            event(new RecoveryCodeReplaced($user, $match));
        } elseif ($code === '' || ! $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code)) {
            throw ValidationException::withMessages(['code' => 'That code isn\'t valid. Check the time on your phone and try again.']);
        }

        $remember = (bool) $request->session()->pull('partner.login.remember', false);
        $request->session()->forget('partner.login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('partner.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login')->with('success', 'You\'ve signed out.');
    }

    protected function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('partner.login.id');
        $user = $id ? User::find($id) : null;

        return $user && $user->isAffiliateOnly() && $user->hasEnabledTwoFactorAuthentication() ? $user : null;
    }
}
