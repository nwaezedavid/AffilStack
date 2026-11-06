<?php

namespace App\Http\Controllers;

use App\Models\MobileIntegrationSetting;
use Illuminate\Http\JsonResponse;

/**
 * The two files phones check before letting an AffilStack web link open
 * the app instead of the browser: Apple's apple-app-site-association and
 * Android's assetlinks.json, under /.well-known/. Only the pages the app
 * has its own screen for are claimed (config('mobile.app_links')); sign-in
 * (/mobile/authorize) and everything else stays in the browser. Each file
 * is empty (404) until its ID is entered in admin (Site > App Push &
 * Links), so nothing changes for anyone before the apps are published.
 */
class AppLinksController extends Controller
{
    public function apple(): JsonResponse
    {
        $teamId = MobileIntegrationSetting::current()->ios_team_id;

        abort_if(blank($teamId), 404);

        $appId = $teamId.'.'.config('mobile.ios_bundle_id');

        $components = [
            ['/' => '/mobile/*', 'exclude' => true, 'comment' => 'Sign-in always stays in the browser.'],
            ...array_map(fn (string $path): array => ['/' => $path], (array) config('mobile.app_links')),
        ];

        return $this->json([
            'applinks' => [
                'details' => [
                    ['appIDs' => [$appId], 'components' => $components],
                ],
            ],
            'webcredentials' => ['apps' => [$appId]],
        ]);
    }

    public function android(): JsonResponse
    {
        $fingerprints = MobileIntegrationSetting::current()->androidFingerprints();

        abort_if($fingerprints === [], 404);

        return $this->json([[
            'relation' => ['delegate_permission/common.handle_all_urls', 'delegate_permission/common.get_login_creds'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('mobile.android_package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    /**
     * Apple and Google fetch these through their own caches; keep the
     * answer short-lived so a change in admin shows up within the hour.
     *
     * @param  array<int|string, mixed>  $payload
     */
    protected function json(array $payload): JsonResponse
    {
        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
