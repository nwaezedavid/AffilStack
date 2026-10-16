<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use App\Models\FeaturePage;
use App\Models\PageSeo;
use App\Models\Plan;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\Tutorial;
use App\Support\Seo\PublicPages;
use App\Support\Seo\RobotsTxt;
use App\Support\SiteDomains;
use App\Support\Translation\Languages;
use App\Support\Translation\LocalizedUrls;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * The two crawler-facing files an admin needs to control without a code
 * deploy. robots.txt is free text edited in SEO > SEO settings (a Sitemap
 * line is always guaranteed); the sitemap is generated from the public
 * page registry (PublicPages) plus every published SitePage, skipping
 * anything set to noindex or canonicalised to another URL. The WordPress
 * blog subdomain ships and indexes its own sitemap separately.
 */
class SeoController extends Controller
{
    public function robots(Request $request): Response
    {
        // The customer app (app.affilstack.com) is all logins and private
        // dashboards: nothing there should be crawled.
        $content = SiteDomains::isAppRequest($request)
            ? "User-agent: *\nDisallow: /\n"
            : RobotsTxt::content();

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [];
        $priorities = [
            'home' => '1.0',
            'registration.pricing' => '0.9',
            'affiliate.landing' => '0.9',
            'tutorials.index' => '0.7',
            'help.index' => '0.5',
            'features.index' => '0.8',
            'faq.index' => '0.6',
            'about' => '0.5',
            'contact.show' => '0.5',
        ];

        foreach (array_keys(PublicPages::all()) as $routeName) {
            if (! PublicPages::isLive($routeName) || ! $this->routePageIsIndexable($routeName)) {
                continue;
            }

            $urls[] = array_filter([
                'loc' => route($routeName),
                'priority' => $priorities[$routeName] ?? '0.5',
                'lastmod' => $this->routeLastModified($routeName)?->toAtomString(),
            ]);
        }

        // Agent 11: one URL per published feature page (Website → Features).
        foreach (FeaturePage::publishedList() as $featurePage) {
            $urls[] = ['loc' => $featurePage->url(), 'priority' => '0.7', 'lastmod' => $featurePage->updated_at?->toAtomString()];
        }

        if ($blogUrl = SiteSetting::get('seo_blog_url')) {
            $urls[] = ['loc' => rtrim($blogUrl, '/'), 'priority' => '0.8'];
        }

        // Static admin-editable pages (Terms, Privacy, custom pages). "about"
        // is a route page above. A noindex page is deliberately kept out of
        // search results, and a page canonicalised elsewhere isn't the URL
        // Google should index — neither belongs here.
        $sitePages = SitePage::where('is_published', true)->where('no_index', false)->where('slug', '!=', 'about')->get();

        foreach ($sitePages as $page) {
            $loc = url("/{$page->slug}");

            if ($page->canonical_url && rtrim($page->canonical_url, '/') !== rtrim($loc, '/')) {
                continue;
            }

            $urls[] = ['loc' => $loc, 'priority' => '0.4', 'lastmod' => $page->updated_at->toAtomString()];
        }

        // Feature 6: each website page's address in every live language.
        foreach ($urls as $index => $url) {
            $urls[$index]['alternates'] = LocalizedUrls::alternatesFor($url['loc']);

            foreach ($urls[$index]['alternates'] as $code => $href) {
                if ($code !== Languages::SOURCE && rtrim($href, '/') !== rtrim($url['loc'], '/')) {
                    $urls[] = ['loc' => $href, 'priority' => $url['priority'], 'lastmod' => $url['lastmod'] ?? null, 'alternates' => $urls[$index]['alternates']];
                }
            }
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * About keeps honouring its legacy SitePage row's publish/noindex flags
     * (an unpublished or noindexed About page must not be listed), on top
     * of the Page SEO robots and canonical settings every route page has.
     */
    protected function routePageIsIndexable(string $routeName): bool
    {
        $seo = PageSeo::forRoute($routeName);

        if ($seo && ! ($seo->robots_index ?? true)) {
            return false;
        }

        if ($seo?->canonical_url && rtrim($seo->canonical_url, '/') !== rtrim(route($routeName), '/')) {
            return false;
        }

        if ($routeName === 'about') {
            $aboutPage = SitePage::where('slug', 'about')->first();

            return ! $aboutPage || ($aboutPage->is_published && ! $aboutPage->no_index);
        }

        return true;
    }

    /**
     * When the page's content (or its SEO) last changed, from the records it
     * is actually rendered from — omitted rather than guessed when unknown.
     */
    protected function routeLastModified(string $routeName): ?CarbonInterface
    {
        $dates = [PageSeo::where('route_name', $routeName)->value('updated_at')];

        $dates[] = match ($routeName) {
            'registration.pricing' => Plan::max('updated_at'),
            'help.index', 'faq.index' => FaqItem::max('updated_at'),
            'features.index' => FeaturePage::max('updated_at'),
            'tutorials.index' => Tutorial::max('updated_at'),
            'about' => SitePage::where('slug', 'about')->value('updated_at'),
            default => null,
        };

        // Settings-driven pages: only the settings that page is built from.
        $settingPrefixes = match ($routeName) {
            'home' => ['hero_', 'home_', 'homepage_', 'site_name'],
            'about' => ['about_'],
            'contact.show' => ['contact_', 'support_email'],
            'affiliate.landing' => ['affiliate_'],
            default => [],
        };

        if ($settingPrefixes !== []) {
            $dates[] = SiteSetting::query()
                ->where(function ($query) use ($settingPrefixes) {
                    foreach ($settingPrefixes as $prefix) {
                        $query->orWhere('key', 'like', $prefix.'%');
                    }
                })
                ->max('updated_at');
        }

        return collect($dates)
            ->filter()
            ->map(fn ($date) => $date instanceof CarbonInterface ? $date : Carbon::parse($date))
            ->max();
    }
}
