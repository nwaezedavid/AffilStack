<?php

namespace App\Http\Controllers;

use App\Support\MobileApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The /app smart link — one URL to put on flyers, emails, QR codes and
 * social bios. Android phones go to Google Play, iPhones and iPads to the
 * App Store, and everyone else (or a store with no link yet) lands on the
 * homepage's apps section. See App\Support\MobileApps.
 */
class MobileAppRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $storeUrl = MobileApps::storeUrlForUserAgent($request->userAgent());

        if ($storeUrl) {
            return redirect()->away($storeUrl);
        }

        return redirect()->to(route('home').(MobileApps::isVisible() ? '#get-the-app' : ''));
    }
}
