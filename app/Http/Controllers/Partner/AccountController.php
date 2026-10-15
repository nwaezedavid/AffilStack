<?php

namespace App\Http\Controllers\Partner;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Account & security for partners: name, password, leaderboard
 * visibility and optional authenticator-app two-factor sign-in (Fortify's
 * own actions, so it is the same two-factor the customer app uses).
 * Turning two-factor on or off asks for the current password.
 */
class AccountController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request): View
    {
        $user = $request->user();
        $pending = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;

        return view('partner.account', [
            'user' => $user,
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'twoFactorPending' => $pending,
            'qrCode' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => session('show_recovery_codes') && $user->two_factor_recovery_codes ? $user->recoveryCodes() : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'show_on_partner_leaderboard' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'show_on_partner_leaderboard' => $request->boolean('show_on_partner_leaderboard'),
        ]);

        return back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], ['current_password.current_password' => 'Your current password is incorrect.']);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('success', 'Password changed.');
    }

    public function enableTwoFactor(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $this->confirmPassword($request);

        $enable($request->user());

        return redirect()->to(route('partner.account').'#two-factor')->with('success', 'Scan the QR code with your authenticator app, then enter the 6-digit code to finish.');
    }

    public function confirmTwoFactor(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        try {
            $confirm($request->user(), preg_replace('/\s+/', '', (string) $request->input('code')));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => 'That code isn\'t valid. Check the time on your phone and try again.']);
        }

        return redirect()->to(route('partner.account').'#two-factor')
            ->with('success', 'Two-step sign-in is on. Save your recovery codes somewhere safe.')
            ->with('show_recovery_codes', true);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $this->confirmPassword($request);

        $generate($request->user());

        return redirect()->to(route('partner.account').'#two-factor')
            ->with('success', 'New recovery codes created. The old ones no longer work.')
            ->with('show_recovery_codes', true);
    }

    public function disableTwoFactor(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $this->confirmPassword($request);

        $disable($request->user());

        return redirect()->to(route('partner.account').'#two-factor')->with('success', 'Two-step sign-in is off.');
    }

    protected function confirmPassword(Request $request): void
    {
        $request->validate(
            ['password' => ['required', 'string', 'current_password:web']],
            ['password.current_password' => 'Your password is incorrect.', 'password.required' => 'Enter your password to change two-step sign-in.'],
        );
    }
}
