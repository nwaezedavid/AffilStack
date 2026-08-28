<?php

namespace App\Http\Controllers;

use App\Models\TrackedLink;
use App\Services\Links\LinkCloakingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated redirect for every cloaked /go/{code} link —
 * whoever clicks one is the offer's own audience, not an AffiliStack user.
 * See LinkCloakingService for how these links get created.
 */
class LinkController extends Controller
{
    public function redirect(string $code, Request $request, LinkCloakingService $service): RedirectResponse
    {
        $link = TrackedLink::where('code', $code)->firstOrFail();

        $service->recordClick($link, $request);

        return redirect()->away($link->destination_url);
    }
}
