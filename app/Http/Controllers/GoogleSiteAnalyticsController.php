<?php

namespace App\Http\Controllers;

use App\Services\Analytics\GoogleSiteAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The admin-only "Connect with Google" OAuth dance for the Site Analytics
 * settings page (Filament: Site > Site Analytics) — a plain, non-Filament
 * route pair since Google redirects the browser here directly, same
 * pattern as Dashboard\EmailConnectionController's Gmail connect flow and
 * CreativeTaskPreviewController's admin-only, inline-guarded plain route.
 */
class GoogleSiteAnalyticsController extends Controller
{
    protected const SESSION_STATE_KEY = 'google_site_analytics_connect_state';

    public function redirect(Request $request, GoogleSiteAnalyticsService $service): RedirectResponse
    {
        abort_unless($request->user()?->canAccessDepartment('site'), 403);

        if (! $service->isAvailable()) {
            return redirect()->route('filament.admin.pages.google-site-analytics-settings')
                ->with('error', 'Set up a Google OAuth client id/secret under Site > Google Login first — Site Analytics reuses the same one.');
        }

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);

        return redirect()->away($service->authorizationUrl(route('google-site-analytics.callback'), $state));
    }

    public function callback(Request $request, GoogleSiteAnalyticsService $service): RedirectResponse
    {
        abort_unless($request->user()?->canAccessDepartment('site'), 403);

        $state = $request->session()->pull(self::SESSION_STATE_KEY);
        $settingsUrl = route('filament.admin.pages.google-site-analytics-settings');

        if (! $state || $request->query('state') !== $state || ! $request->query('code')) {
            return redirect($settingsUrl)->with('error', 'Google connection failed — please try again.');
        }

        try {
            $service->handleCallback((string) $request->query('code'), route('google-site-analytics.callback'));
        } catch (RuntimeException $e) {
            report($e);

            return redirect($settingsUrl)->with('error', $e->getMessage());
        }

        return redirect($settingsUrl)->with('success', 'Connected to Google — now set up Analytics, Search Console, and Tag Manager below.');
    }
}
