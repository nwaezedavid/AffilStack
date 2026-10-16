<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "app-scope:offers:read" — a connected app's token must have been
 * granted that permission by the customer. Other tokens pass.
 */
class EnsureAppScope
{
    public function handle(Request $request, Closure $next, string ...$scope): Response
    {
        $scope = implode(':', $scope);
        $token = $request->attributes->get('apiToken');

        if ($token instanceof ApiToken && ! $token->allows($scope)) {
            return response()->json([
                'message' => "This app hasn't been given the \"{$scope}\" permission.",
                'required_scope' => $scope,
            ], 403);
        }

        return $next($request);
    }
}
