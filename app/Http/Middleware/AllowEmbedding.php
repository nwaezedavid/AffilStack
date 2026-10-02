<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a route as embeddable on any website (the affiliate banner iframe
 * and its loader script). SecurityHeaders — which runs outside every route
 * middleware and would otherwise overwrite anything set here — reads this
 * flag and, for these responses only, drops X-Frame-Options and sends
 * `frame-ancestors *` instead of `'self'`.
 */
class AllowEmbedding
{
    public const ATTRIBUTE = 'security.allow_framing';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        return $next($request);
    }
}
