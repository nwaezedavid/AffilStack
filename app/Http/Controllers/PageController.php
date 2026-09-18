<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders any published SitePage (About, Terms, Privacy, etc.) by slug.
 * Routes bind the slug via ->defaults('slug', ...) so each page keeps a
 * clean, dedicated URL while sharing one admin-editable content system.
 */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        // Audit item #7 (caching/performance) — see SitePage::published().
        $page = SitePage::published($slug) ?? throw new NotFoundHttpException;

        return view('marketing.page', compact('page'));
    }
}
