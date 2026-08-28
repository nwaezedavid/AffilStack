<?php

namespace App\Http\Controllers;

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
}
