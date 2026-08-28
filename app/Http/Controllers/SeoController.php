<?php

namespace App\Http\Controllers;

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
            ['loc' => route('help.index'), 'priority' => '0.5'],
        ];

        if ($blogUrl = SiteSetting::get('seo_blog_url')) {
            $urls[] = ['loc' => rtrim($blogUrl, '/'), 'priority' => '0.8'];
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
