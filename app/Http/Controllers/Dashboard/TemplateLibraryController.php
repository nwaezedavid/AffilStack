<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's template library: prompts, emails, posts, hooks and
 * scripts installed from template packs, ready to copy.
 */
class TemplateLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $kinds = config('marketplace.template_kinds');
        $kind = (string) $request->query('kind', '');

        $templates = $request->user()->userTemplates()
            ->whereHas('install', fn ($query) => $query->whereIn('status', ['active', 'past_due']))
            ->when(array_key_exists($kind, $kinds), fn ($query) => $query->where('kind', $kind))
            ->with('install.addon.liveVersion')
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        return view('dashboard.templates.index', ['templates' => $templates, 'kinds' => $kinds, 'kind' => $kind]);
    }
}
