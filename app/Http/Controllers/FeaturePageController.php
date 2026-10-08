<?php

namespace App\Http\Controllers;

use App\Models\FeaturePage;
use App\Services\Seo\SiteNotFoundResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public feature pages: /features (every tool, grouped like the
 * header's Tools menu) and /features/{slug} (one tool in depth, with
 * screenshots, an optional video per section, steps and FAQs). Content
 * is edited in Website → Features (FeaturePage).
 */
class FeaturePageController extends Controller
{
    public function __construct(protected SiteNotFoundResponder $responder) {}

    public function index(): View
    {
        return view('marketing.features.index', [
            'groups' => FeaturePage::menuGroups(),
            'pages' => FeaturePage::publishedList(),
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $pages = FeaturePage::publishedList();
        $page = $pages->firstWhere('slug', $slug);

        if (! $page) {
            return $this->responder->respond($request, 'features/'.$slug);
        }

        $related = $pages->where('menu_group', $page->menu_group)->where('slug', '!=', $page->slug)->take(3)->values();

        if ($related->count() < 3) {
            $related = $related->concat($pages->where('menu_group', '!=', $page->menu_group)->take(3 - $related->count()))->values();
        }

        return view('marketing.features.show', compact('page', 'related'));
    }
}
