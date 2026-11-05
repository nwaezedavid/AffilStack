<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signing out from the app: just this phone, or every phone signed in to
 * the account ("Sign out everywhere").
 */
class SessionController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobileDevice')->revoke();

        return response()->json(['message' => 'Signed out.']);
    }

    public function logoutEverywhere(Request $request): JsonResponse
    {
        MobileDevice::revokeAllFor($request->user());

        return response()->json(['message' => 'Every phone signed in to your account has been signed out.']);
    }
}
