<?php

namespace App\Http\Controllers;

use App\Support\SiteDomains;
use App\Support\Translation\Languages;
use App\Support\Translation\LocalizedUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The language switcher (dashboard, partner portal and sign-in pages) and
 * the "No, stay in English" answer to the language suggestion.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'in:'.implode(',', array_keys(Languages::switcherOptions()))]]);
        $locale = $data['locale'];

        $request->user()?->forceFill(['locale' => $locale === Languages::SOURCE ? null : $locale])->save();

        $redirect = $this->back($request, $locale);

        return $redirect
            ->withCookie(cookie(Languages::COOKIE, $locale, 60 * 24 * 365, '/', SiteDomains::sharedCookieDomain(), null, false))
            ->withCookie(cookie()->forget(Languages::DISMISSED_COOKIE, '/', SiteDomains::sharedCookieDomain()));
    }

    public function stay(Request $request): RedirectResponse
    {
        $return = (string) $request->query('return', '/');

        if (! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, '\\')) {
            $return = '/';
        }

        return redirect()->to($return)
            ->withCookie(cookie(Languages::DISMISSED_COOKIE, '1', 60 * 24 * 365, '/', SiteDomains::sharedCookieDomain(), null, false));
    }

    /**
     * Back where they were; on the website, to that page's address in the
     * new language.
     */
    protected function back(Request $request, string $locale): RedirectResponse
    {
        $previous = url()->previous();
        $parts = parse_url($previous);
        $ownHosts = array_filter([$request->getHost(), SiteDomains::marketingHost(), SiteDomains::appHost(), SiteDomains::affiliateHost()]);

        // Only ever back to one of AffilStack's own sites.
        if (! is_array($parts) || (isset($parts['host']) && ! in_array(strtolower($parts['host']), array_map('strtolower', $ownHosts), true))) {
            return redirect()->to('/');
        }

        [, $path] = LocalizedUrls::split('/'.ltrim((string) ($parts['path'] ?? '/'), '/'));
        $bare = (isset($parts['host']) ? ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '').$path.(isset($parts['query']) ? '?'.$parts['query'] : '');

        $localized = LocalizedUrls::to($bare, $locale);

        return redirect()->to($localized ?? $previous);
    }
}
