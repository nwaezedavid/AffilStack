<?php

namespace App\Http\Controllers\Api\WordPress;

use App\Http\Controllers\Controller;
use App\Models\WordpressSite;
use App\Services\WordPress\ConnectorSignature;
use App\Services\WordPress\PluginPackager;
use App\Services\WordPress\WordPressSiteService;
use App\Support\SiteDomains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The AffilStack side of the AffilStack Connector plugin's own calls:
 *
 * - exchange      the plugin swaps its one-time connect code for the
 *                 site's secret (server to server, never via a browser);
 * - plugin-info   update check: the current version and a short-lived
 *                 download link, for connected sites on a plan that
 *                 includes the plugin;
 * - disconnect    the customer disconnected from inside WordPress.
 *
 * Everything except exchange must be signed with the site's secret.
 */
class ConnectorController extends Controller
{
    public function exchange(Request $request, WordPressSiteService $sites): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:48'],
            'site_url' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:200'],
            'wp_version' => ['nullable', 'string', 'max:20'],
            'plugin_version' => ['nullable', 'string', 'max:20'],
            'seo_plugin' => ['nullable', 'string', 'max:30'],
        ]);

        $site = $sites->exchange($data['code'], $data);

        if (! $site) {
            return response()->json(['code' => 'affilstack_invalid_code', 'message' => 'This connection link has expired or was already used. Click "Connect to AffilStack" again.'], 422);
        }

        return response()->json([
            'site_id' => $site->id,
            'secret' => $site->secret,
            'account_name' => $site->user?->name,
            'dashboard_url' => route('wordpress.index'),
        ]);
    }

    public function pluginInfo(Request $request, WordPressSiteService $sites): JsonResponse
    {
        $site = $this->signedSite($request);

        if (! $site) {
            return response()->json(['code' => 'affilstack_not_connected', 'message' => 'This site isn\'t connected to AffilStack.'], 401);
        }

        $site->update(array_filter([
            'last_seen_at' => now(),
            'plugin_version' => $request->header('X-AffilStack-Plugin-Version') ? mb_substr((string) $request->header('X-AffilStack-Plugin-Version'), 0, 20) : null,
        ]));

        $included = $site->user && $sites->included($site->user);

        return response()->json([
            'name' => 'AffilStack Connector',
            'slug' => config('wordpress_plugin.slug'),
            'version' => PluginPackager::version(),
            'requires' => config('wordpress_plugin.requires_wp'),
            'tested' => config('wordpress_plugin.tested_wp'),
            'requires_php' => config('wordpress_plugin.requires_php'),
            'homepage' => SiteDomains::publicUrl('/'),
            'changelog' => config('wordpress_plugin.changelog'),
            'download_url' => $included
                ? URL::temporarySignedRoute('wordpress.plugin.package', now()->addHours(6), ['version' => PluginPackager::version()])
                : null,
        ]);
    }

    public function package(PluginPackager $packager): BinaryFileResponse|JsonResponse
    {
        try {
            return response()->download($packager->zipPath(), PluginPackager::fileName(), ['Content-Type' => 'application/zip']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function disconnect(Request $request, WordPressSiteService $sites): JsonResponse
    {
        $site = $this->signedSite($request);

        if ($site) {
            $sites->disconnect($site);
        }

        return response()->json(['disconnected' => true]);
    }

    /**
     * The connected site a request is signed by, or null.
     */
    protected function signedSite(Request $request): ?WordpressSite
    {
        $id = (string) $request->header(ConnectorSignature::SITE_HEADER);
        $site = ctype_digit($id) ? WordpressSite::query()->connected()->find((int) $id) : null;

        if (! $site || ! ConnectorSignature::verify(
            (string) $site->secret,
            (string) $request->header(ConnectorSignature::TIMESTAMP_HEADER),
            (string) $request->header(ConnectorSignature::SIGNATURE_HEADER),
            $request->method(),
            '/'.$request->path(),
            $request->getContent(),
        )) {
            return null;
        }

        return $site;
    }
}
