<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();

        $qrSvg = null;
        $recoveryCodes = null;

        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $qrSvg = $user->twoFactorQrCodeSvg();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $recoveryCodes = $user->recoveryCodes();
        }

        return view('dashboard.profile', compact('user', 'qrSvg', 'recoveryCodes'));
    }

    /**
     * Audit gap #4: self-service account deletion, soft-delete + 30-day
     * grace period. Soft-deleting immediately makes the account invisible
     * to every normal User:: lookup the app makes (Fortify's login query
     * included) via Eloquent's global SoftDeletes scope — nothing else
     * needs to change to "block login" or "freeze credits". A daily
     * command (see PurgeDeletedAccounts) hard-deletes it 30 days later
     * unless an admin restores it first from the Users resource.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Your account has been deactivated. Contact support within the next 30 days if you\'d like it restored.');
    }
}
