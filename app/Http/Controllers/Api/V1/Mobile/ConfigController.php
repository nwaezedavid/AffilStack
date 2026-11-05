<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use App\Support\MobileApps;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/mobile/config — what the app checks every time it opens,
 * before anyone signs in: whether this version must update, the banner
 * to show, store links and where sign-in happens. Public and secret-free.
 * In-app purchases are off: billing is managed on the website (owner's
 * decision for version 1).
 */
class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'brand' => [
                'name' => Brand::name(),
                'icon_url' => Brand::iconUrl(),
            ],
            'versions' => [
                'ios' => ['minimum' => MobileApps::minSupportedVersion('ios'), 'latest' => MobileApps::latestVersion()],
                'android' => ['minimum' => MobileApps::minSupportedVersion('android'), 'latest' => MobileApps::latestVersion()],
            ],
            'store_urls' => [
                'ios' => MobileApps::appStoreUrl(),
                'android' => MobileApps::playStoreUrl(),
            ],
            'announcement' => MobileApps::announcement(),
            'auth' => [
                'client_id' => config('mobile.client_id'),
                'authorize_url' => route('mobile.authorize'),
                'token_url' => route('api.oauth.token'),
                'revoke_url' => route('api.oauth.revoke'),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'billing' => route('billing.index'),
                'forgot_password' => route('password.request'),
                'privacy' => route('privacy'),
                'terms' => route('terms'),
            ],
            'features' => [
                'in_app_purchases' => false,
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
