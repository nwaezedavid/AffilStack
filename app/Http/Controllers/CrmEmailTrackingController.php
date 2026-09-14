<?php

namespace App\Http\Controllers;

use App\Models\CrmEmailSend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * CRM/email dashboard: public, unauthenticated — both routes are hit by
 * the CRM contact's own email client/browser, never by an AffilStack user.
 * See CrmEmailService for how these tokens and link_destination values are
 * created.
 */
class CrmEmailTrackingController extends Controller
{
    /**
     * 1x1 transparent PNG, requested by the recipient's mail client when it
     * loads remote images — the classic open-tracking pixel. Always
     * returns the pixel even for an unknown/expired token, so a client
     * retrying or caching never surfaces a broken image.
     */
    protected const TRANSPARENT_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function open(string $token): Response
    {
        CrmEmailSend::where('tracking_token', $token)->first()?->recordOpen();

        return response(base64_decode(self::TRANSPARENT_PNG_BASE64))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Redirects to link_destination, a value CrmEmailService resolved and
     * stored on this row at send time — never taken from this request —
     * so this route can never be turned into an open redirect by a
     * crafted query string.
     */
    public function click(string $token): RedirectResponse
    {
        $send = CrmEmailSend::where('tracking_token', $token)->firstOrFail();

        $send->recordClick();

        abort_unless($send->link_destination, 404);

        return redirect()->away($send->link_destination);
    }
}
