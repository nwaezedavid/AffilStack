<?php

namespace App\Http\Middleware;

use App\Support\SiteDomains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * www.affilstack.com → affilstack.com (301, same path and query string).
 *
 * The public-website routes are registered for the APP_URL host only (see
 * SiteDomains::marketing()), so a request for the www. form matched no
 * route and got a 404. Runs before routing, for every method, so old links,
 * bookmarks and search results all land on the canonical address.
 */
class RedirectWwwToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalHost = strtolower(SiteDomains::marketingHost());

        if (str_starts_with($canonicalHost, 'www.') || strtolower($request->getHost()) !== 'www.'.$canonicalHost) {
            return $next($request);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $query = $request->getQueryString();

        $target = $scheme.'://'.$canonicalHost.($port ? ':'.$port : '')
            .$request->getBaseUrl().$request->getPathInfo()
            .($query !== null && $query !== '' ? '?'.$query : '');

        return redirect()->to($target, $request->isMethodSafe() ? 301 : 308);
    }
}
