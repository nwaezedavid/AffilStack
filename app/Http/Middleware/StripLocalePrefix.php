<?php

namespace App\Http\Middleware;

use App\Support\Translation\Languages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * affilstack.com/fr/pricing is the French version of /pricing: before
 * routing, the language code is taken off the address (and remembered on
 * the request) so the normal route answers. /en/… redirects to the plain
 * English address.
 */
class StripLocalePrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        if (preg_match('#^/([a-z]{2}(?:-[a-z0-9]{2,4})?)(/.*)?$#i', $request->getPathInfo(), $match)) {
            $code = strtolower($match[1]);
            $rest = $match[2] ?? '/';
            $query = $request->getQueryString();

            if ($code === Languages::SOURCE && $request->isMethodSafe()) {
                return redirect()->to(rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/').$rest.($query ? '?'.$query : ''), 301);
            }

            if (Languages::exists($code)) {
                $server = $request->server->all();
                $server['REQUEST_URI'] = $request->getBaseUrl().$rest.($query ? '?'.$query : '');

                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    array_merge($request->attributes->all(), ['locale_prefix' => $code]),
                    $request->cookies->all(),
                    $request->files->all(),
                    $server,
                    $request->getContent(),
                );
            }
        }

        return $next($request);
    }
}
