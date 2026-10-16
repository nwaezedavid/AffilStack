<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\WordpressSite;
use App\Services\WordPress\WordPressDispatcher;
use App\Services\WordPress\WordPressSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * "Send to WordPress" on a finished blog article.
 */
class WordPressSendController extends Controller
{
    public function store(Request $request, Generation $generation, WordPressSiteService $sites, WordPressDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($generation->isAccessibleBy($request->user()), 403);

        $data = $request->validate(['site_id' => ['required', 'integer']]);

        $site = WordpressSite::query()
            ->whereKey($data['site_id'])
            ->where('user_id', $sites->owner($request->user())->id)
            ->first();

        if (! $site) {
            return back()->with('error', 'Choose one of your connected WordPress sites.');
        }

        try {
            $dispatcher->send($request->user(), $generation, $site);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sending to '.$site->displayName().'. It will be there as a draft in a moment.');
    }
}
