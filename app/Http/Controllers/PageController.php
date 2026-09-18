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

        if (! $page) {
            return $this->responder->respond($request, $slug);
        }

        return view('marketing.page', compact('page'));
    }
}
