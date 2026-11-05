<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Services\Account\AccountDeactivator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * DELETE /api/v1/mobile/account — "Delete my account" inside the app, as
 * Apple requires (guideline 5.1.1(v)). Same result as the website's
 * profile page (AccountDeactivator: soft delete, restorable by support for
 * 30 days, subscription stopped). The customer proves it's them with their
 * password — or, for a Google/Apple account that never had one, by having
 * signed in to the app in the last few minutes and typing DELETE.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AccountDeactivator $deactivator): JsonResponse
    {
        $request->validate([
            'password' => ['nullable', 'string', 'max:255'],
            'confirm' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobileDevice');

        $password = (string) $request->input('password', '');
        $recentlySignedIn = $device->authenticated_at !== null
            && $device->authenticated_at->gte(now()->subMinutes((int) config('mobile.recent_sign_in_minutes')));

        $verified = $password !== ''
            ? Hash::check($password, (string) $user->password)
            : $recentlySignedIn && $request->input('confirm') === 'DELETE';

        if (! $verified) {
            throw ValidationException::withMessages([
                'password' => $password !== ''
                    ? 'That password is not correct.'
                    : 'Enter your password, or sign in again and type DELETE to confirm.',
            ]);
        }

        $deactivator->deactivate($user);

        return response()->json([
            'message' => 'Your account has been deactivated. Contact support within the next 30 days if you\'d like it restored.',
        ]);
    }
}
