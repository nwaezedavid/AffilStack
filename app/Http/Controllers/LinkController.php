<?php

namespace App\Http\Controllers;

use App\Models\TrackedLink;
use App\Services\Links\LinkCloakingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated redirect for every cloaked /go/{code} link —
 * whoever clicks one is the offer's own audience, not an AffilStack user.
 * See LinkCloakingService for how these links get created.
 */
class LinkController extends Controller
{
    public function redirect(string $code, Request $request, LinkCloakingService $service): RedirectResponse
    {
        $link = TrackedLink::where('code', $code)->firstOrFail();

        $service->recordClick($link, $request);

        return redirect()->away($this->withTrackingParam($link->destination_url, $code));
    }

    /**
     * Feature 2 (conversion & earnings tracker) needs this link's code to
     * survive the redirect into the affiliate network's own reporting, so a
     * later CSV import can match a payout back to tracked_link_id — see
     * config/earnings.php. Never overwrites a same-named parameter the
     * offer's own destination URL already has; that's the user's own value
     * and takes priority.
     */
    protected function withTrackingParam(string $destinationUrl, string $code): string
    {
        $param = config('earnings.tracking_param');
        $parts = parse_url($destinationUrl);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $destinationUrl;
        }

        parse_str($parts['query'] ?? '', $query);

        if (array_key_exists($param, $query)) {
            return $destinationUrl;
        }

        $query[$param] = $code;

        $rebuilt = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .'?'.http_build_query($query)
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        return $rebuilt;
    }
}
