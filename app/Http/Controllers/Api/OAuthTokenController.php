<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\OAuthServer;
use App\Services\Mobile\MobileAuthServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/oauth/token and /api/oauth/revoke for connected apps — and for
 * the official AffilStack phone apps, which identify themselves with the
 * mobile client_id and no secret (PKCE instead; see MobileAuthServer).
 * Requests from connected apps are handled exactly as before.
 */
class OAuthTokenController extends Controller
{
    public function token(Request $request, OAuthServer $server, MobileAuthServer $mobile): JsonResponse
    {
        if ($mobile->isMobileClient((string) $request->input('client_id', ''))) {
            [$status, $body] = $mobile->token($request->only(['grant_type', 'code', 'redirect_uri', 'code_verifier', 'refresh_token', 'device_name', 'platform', 'app_version']));

            return response()->json($body, $status, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        }

        [$status, $body] = $server->token(array_merge($request->only(['grant_type', 'code', 'redirect_uri', 'code_verifier', 'refresh_token']), $this->client($request)));

        return response()->json($body, $status, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    public function revoke(Request $request, OAuthServer $server, MobileAuthServer $mobile): JsonResponse
    {
        if ($mobile->isMobileClient((string) $request->input('client_id', ''))) {
            $mobile->revoke((string) $request->input('token', ''));

            return response()->json([], 200);
        }

        $client = $this->client($request);

        if (! $server->revoke($client['client_id'], $client['client_secret'], (string) $request->input('token', ''))) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        return response()->json([], 200);
    }

    /**
     * Client credentials from the body or HTTP Basic auth.
     *
     * @return array{client_id: string, client_secret: string}
     */
    protected function client(Request $request): array
    {
        $id = (string) $request->input('client_id', '');
        $secret = (string) $request->input('client_secret', '');

        if (($id === '' || $secret === '') && $request->getUser() !== null) {
            $id = rawurldecode((string) $request->getUser());
            $secret = rawurldecode((string) $request->getPassword());
        }

        return ['client_id' => $id, 'client_secret' => $secret];
    }
}
