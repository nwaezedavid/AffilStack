<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Http\Response;

/**
 * The two crawler-facing files an admin needs to control without a code
 * deploy. robots.txt is free text edited in the SEO admin page; the sitemap
 * is generated from the app's own public routes — the WordPress blog
 * subdomain ships and indexes its own sitemap separately.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $default = "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response(SiteSetting::get('seo_robots_txt', $default), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('registration.pricing'), 'priority' => '0.9'],
            ['loc' => route('tutorials.index'), 'priority' => '0.7'],
            ['loc' => route('help.index'), 'priority' => '0.5'],
        ];

        // "about" gets its own hardcoded entry (rather than falling through
        // to the SitePage loop below like every other static page) purely
        // so it's never missing on a fresh install with no site_pages rows
        // yet. That's the ONLY reason it's special-cased — once a real About
        // SitePage row exists, it must respect is_published/no_index exactly
        // like Terms/Privacy/etc. do, or toggling either one on the About
        // page would silently do nothing in the sitemap (a real bug this
        // fixes: it used to always appear regardless of both flags, which
        // could list an unpublished About page as a "soft 404" in Search
        // Console).
        $aboutPage = SitePage::where('slug', 'about')->first();
        if (! $aboutPage || ($aboutPage->is_published && ! $aboutPage->no_index)) {
            $urls[] = array_filter([
                'loc' => route('about'),
                'priority' => '0.5',
                'lastmod' => $aboutPage?->updated_at?->toAtomString(),
            ]);
        }

        // The affiliate landing page is its own acquisition channel (task:
        // "design the best landing page for the affiliate program") — worth
        // the same priority tier as pricing since both are conversion entry
        // points, not just informational. Dropped from the sitemap entirely
        // while an admin has turned the in-house program off (Filament:
        // Billing > Affiliate Program) — see AffiliateController::show().
        if (SiteSetting::flag('affiliate_program_enabled')) {
            $urls[] = ['loc' => route('affiliate.landing'), 'priority' => '0.9'];
        }

        if ($blogUrl = SiteSetting::get('seo_blog_url')) {
            $urls[] = ['loc' => rtrim($blogUrl, '/'), 'priority' => '0.8'];
        }

        // Static admin-editable pages (Terms, Privacy, etc.) — see
        // PageController/SitePage. "about" is excluded here — it's already
        // hardcoded above with its own priority so it's never missing from
        // the sitemap even on a fresh install with no site_pages rows yet.
        // A no_index page is deliberately kept out of search results, so it
        // has no business being listed here either.
        foreach (SitePage::where('is_published', true)->where('no_index', false)->where('slug', '!=', 'about')->get() as $page) {
            $urls[] = ['loc' => url("/{$page->slug}"), 'priority' => '0.4', 'lastmod' => $page->updated_at->toAtomString()];
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
