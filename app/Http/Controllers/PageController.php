<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use Illuminate\View\View;

/**
 * Renders any published SitePage (About, Terms, Privacy, etc.) by slug.
 * Routes bind the slug via ->defaults('slug', ...) so each page keeps a
 * clean, dedicated URL while sharing one admin-editable content system.
 */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = SitePage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('marketing.page', compact('page'));
    }
}
