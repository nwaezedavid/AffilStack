<?php

namespace App\Http\Middleware;

use App\Support\Security\PhpRuntime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes which PHP version the website is served with, so the daily
 * security scan checks the PHP visitors actually reach — on Hostinger the
 * cron's command-line PHP can be a different build. Runs after the
 * response is sent, and only writes when something changed.
 */
class RecordPhpRuntime
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        PhpRuntime::recordCurrent();
    }
}
