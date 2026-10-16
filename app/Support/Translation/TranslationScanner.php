<?php

namespace App\Support\Translation;

use App\Models\FeaturePage;
use App\Models\SitePage;
use App\Support\Seo\PublicPages;
use App\Support\SiteDomains;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Throwable;

/**
 * Visits every public website page in each language (inside the app, no
 * network), so all their text is found and queued for translation before
 * any visitor arrives. Dashboard and portal text is found as customers
 * use them.
 */
class TranslationScanner
{
    /**
     * @return array<int, string> paths
     */
    public function paths(): array
    {
        $paths = [];

        foreach (array_keys(PublicPages::all()) as $routeName) {
            if (PublicPages::isLive($routeName) && ($url = PublicPages::url($routeName))) {
                $paths[] = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            }
        }

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            if (in_array('GET', $route->methods(), true) && LocalizedUrls::routeIsLocalized($route) && $route->parameterNames() === [] && ! preg_match('/\.(xml|txt|json|png|ico)$/', $route->uri())) {
                $paths[] = '/'.ltrim($route->uri(), '/');
            }
        }

        try {
            foreach (FeaturePage::publishedList() as $page) {
                $paths[] = (string) (parse_url($page->url(), PHP_URL_PATH) ?: '/');
            }

            foreach (SitePage::where('is_published', true)->pluck('slug') as $slug) {
                $paths[] = '/'.ltrim((string) $slug, '/');
            }
        } catch (Throwable) {
            // Content tables not ready: the route pages are still scanned.
        }

        $paths = array_values(array_unique(array_map(fn ($path) => '/'.trim($path, '/'), $paths)));

        return array_values(array_filter($paths, fn ($path) => ! in_array($path, ['/app'], true)));
    }

    /**
     * @param  array<int, string>  $locales
     * @return int pages visited
     */
    public function scan(array $locales): int
    {
        $kernel = app(Kernel::class);
        $originalRequest = app('request');
        $sessionDriver = config('session.driver');
        config(['session.driver' => 'array']);
        $visited = 0;

        try {
            foreach ($locales as $locale) {
                if (! Languages::exists($locale)) {
                    continue;
                }

                foreach ($this->paths() as $path) {
                    try {
                        $request = Request::create(SiteDomains::publicUrl('/'.$locale.($path === '/' ? '' : $path)), 'GET');
                        $kernel->handle($request);
                        $visited++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            }
        } finally {
            config(['session.driver' => $sessionDriver]);
            app()->instance('request', $originalRequest);
            app()->setLocale(Languages::SOURCE);
            Mark::$protect = false;
        }

        return $visited;
    }
}
