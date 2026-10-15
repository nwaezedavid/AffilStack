<?php

namespace App\Http\Controllers\Partner;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Forgot your password?" for partners, on the affiliate host. Only ever
 * sends a link to an affiliate-only account, but always answers the same
 * way so the form can't be used to discover who is a partner.
 */
class PasswordResetController extends Controller
{
    use PasswordValidationRules;

    public function request(): View
    {
        return view('partner.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $user = User::where('email', $data['email'])->first();

        if ($user?->isAffiliateOnly()) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('success', 'If that email belongs to a partner account, a reset link is on its way. It expires in 60 minutes.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('partner.auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user?->isAffiliateOnly()) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        return redirect()->route('partner.login')->with('success', 'Password updated. Sign in with your new password.');
    }
}
