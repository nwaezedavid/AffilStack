<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\OAuthServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/oauth/token and /api/oauth/revoke for connected apps.
 */
class OAuthTokenController extends Controller
{
    public function token(Request $request, OAuthServer $server): JsonResponse
    {
        [$status, $body] = $server->token(array_merge($request->only(['grant_type', 'code', 'redirect_uri', 'code_verifier', 'refresh_token']), $this->client($request)));

        return response()->json($body, $status, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    public function revoke(Request $request, OAuthServer $server): JsonResponse
    {
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
