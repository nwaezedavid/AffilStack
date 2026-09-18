<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use App\Services\Seo\SiteNotFoundResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders any published SitePage (About, Terms, Privacy, etc.) by slug.
 * The five original legal-style pages bind their slug via
 * ->defaults('slug', ...) on a dedicated static route; every other
 * admin-created page (any slug typed into Filament: Content > Site Pages)
 * reaches here through the generic /{slug} route registered just above
 * Route::fallback() in routes/web.php.
 *
 * A slug with no published page behind it defers to SiteNotFoundResponder
 * — the same redirect/404-monitor check the catch-all fallback uses — so
 * an unpublished or renamed page never just bare-404s past that safety
 * net.
 */
class PageController extends Controller
{
    public function __construct(protected SiteNotFoundResponder $responder) {}

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        // Audit item #7 (caching/performance) — see SitePage::published().
        $page = SitePage::published($slug);

        // /about is a permanent, structural page like /pricing or /contact
        // now (task #161) — its real content lives in the about_* SiteSetting
        // fields (Site > About Page), not this row's rich-text body. The
        // SitePage row still supplies SEO title/description/OG image when
        // an admin has set them, but its own is_published state never
        // 404s/redirects the page the way it does for a generic admin page.
        if ($slug === 'about') {
            return view('marketing.about', ['page' => $page]);
        }

        if (! $page) {
            return $this->responder->respond($request, $slug);
        }

        return view('marketing.page', compact('page'));
    }
}
