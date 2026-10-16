<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A label, not a check: routes carrying it are public website pages with
 * an address per language (see LocalizedUrls and LocalizeResponse).
 */
class MarkLocalizedUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
