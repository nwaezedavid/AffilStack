<?php

namespace App\Http\Middleware;

use App\Support\SiteDomains;
use App\Support\Translation\HtmlTranslator;
use App\Support\Translation\Languages;
use App\Support\Translation\LocalizedUrls;
use App\Support\Translation\Mark;
use App\Support\Translation\TranslationStore;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Shows each page in the visitor's language.
 *
 * Which language:
 * - public website pages: the address decides (/fr/… is French, no prefix
 *   is English), so every language has its own address for Google;
 * - the dashboard, partner portal and sign-in pages: the signed-in
 *   person's chosen language, otherwise the language they last picked
 *   (a cookie shared across AffilStack's subdomains), otherwise English.
 *
 * The page is rendered as usual, in English, then translated (see
 * HtmlTranslator). English website pages get hreflang links and, for a
 * visitor whose browser prefers another live language, a one-line
 * suggestion to switch.
 */
class LocalizeResponse
{
    public function __construct(protected HtmlTranslator $translator, protected TranslationStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $prefix = $request->attributes->get('locale_prefix');
        $localized = LocalizedUrls::routeIsLocalized($request->route());
        $locale = $this->resolve($request, $prefix, $localized);

        App::setLocale($locale);
        Carbon::setLocale($locale);
        $request->attributes->set('locale', $locale);
        $request->attributes->set('locale_localized_page', $localized);

        if ($prefix !== null && ! $localized) {
            // Only website pages have language addresses; elsewhere the
            // language is remembered instead.
            if (Languages::isLive($prefix)) {
                Cookie::queue(cookie(Languages::COOKIE, $prefix, 60 * 24 * 365, '/', SiteDomains::sharedCookieDomain(), null, false));
            }

            return redirect()->to(LocalizedUrls::current($request, Languages::SOURCE).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 302);
        }

        Mark::$protect = $locale !== Languages::SOURCE && ! $localized && HtmlTranslator::available();

        try {
            $response = $next($request);

            if ($prefix !== null && $localized && Languages::isLive($prefix)) {
                Cookie::queue(cookie(Languages::COOKIE, $prefix, 60 * 24 * 365, '/', SiteDomains::sharedCookieDomain(), null, false));
            }

            return $this->localize($request, $response, $locale, $prefix, $localized);
        } finally {
            Mark::$protect = false;
        }
    }

    protected function resolve(Request $request, ?string $prefix, bool $localized): string
    {
        if ($prefix !== null) {
            return $prefix;
        }

        if ($localized) {
            return Languages::SOURCE;
        }

        $chosen = $request->user()?->locale;

        if (Languages::selectable($chosen)) {
            return $chosen;
        }

        $cookie = $request->cookie(Languages::COOKIE);

        return is_string($cookie) && Languages::selectable($cookie) ? $cookie : Languages::SOURCE;
    }

    protected function localize(Request $request, Response $response, string $locale, ?string $prefix, bool $localized): Response
    {
        try {
            if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
                return $response;
            }

            $html = str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html');

            if ($locale === Languages::SOURCE) {
                if ($localized && $html && $response->isSuccessful() && $request->isMethodSafe()) {
                    $this->decorateEnglishPage($request, $response);
                }

                return $response;
            }

            if ($response instanceof RedirectResponse) {
                if ($prefix !== null && $localized) {
                    $target = LocalizedUrls::to($response->getTargetUrl(), $locale);

                    if ($target !== null) {
                        $response->setTargetUrl($target);
                    }
                }

                return $response;
            }

            $context = '/'.ltrim($request->getPathInfo(), '/');

            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);

                if (is_array($data) && isset($data['html']) && is_string($data['html'])) {
                    $data['html'] = $this->translator->translateFragment($data['html'], $locale, ['context' => $context]);
                    $response->setData($data);
                }

                $response->setContent(Mark::strip((string) $response->getContent()));

                return $response;
            }

            $content = $response->getContent();

            if (! is_string($content)) {
                return $response;
            }

            if (! $html) {
                $response->setContent(Mark::strip($content));

                return $response;
            }

            $head = '';

            if ($localized) {
                $head .= LocalizedUrls::alternates($request);

                if (! Languages::isLive($locale)) {
                    $head .= '<meta name="robots" content="noindex">';
                }
            }

            $response->setContent($this->translator->translateDocument($content, $locale, [
                'context' => $context,
                'head' => $head,
                'rtl' => Languages::isRtl($locale),
                'rewriteUrl' => $prefix !== null && $localized
                    ? fn (string $url, string $method) => LocalizedUrls::to($url, $locale, $method)
                    : null,
            ]));
            $response->headers->remove('Content-Length');
        } catch (Throwable $e) {
            report($e);

            if (! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse && is_string($response->getContent())) {
                $response->setContent(Mark::strip($response->getContent()));
            }
        }

        return $response;
    }

    /**
     * English website page: hreflang links, and the language suggestion.
     */
    protected function decorateEnglishPage(Request $request, Response $response): void
    {
        $content = (string) $response->getContent();

        if (Languages::liveCodes() === []) {
            return;
        }

        $content = preg_replace('#</head>#i', LocalizedUrls::alternates($request).'</head>', $content, 1) ?? $content;

        $suggested = $this->suggestion($request);

        if ($suggested !== null) {
            $bar = view('partials.locale.suggestion', [
                'code' => $suggested,
                'question' => $this->store->line('Read this page in '.Languages::name($suggested).'?', $suggested, 'language suggestion'),
                'yes' => $this->store->line('Yes, switch', $suggested, 'language suggestion'),
                'no' => $this->store->line('No, stay in English', $suggested, 'language suggestion'),
                'switchUrl' => LocalizedUrls::current($request, $suggested),
                'stayUrl' => route('locale.stay', ['return' => '/'.ltrim($request->getPathInfo(), '/')]),
            ])->render();

            $content = preg_replace('#</body>#i', $bar.'</body>', $content, 1) ?? $content;
        }

        $response->setContent($content);
        $response->headers->remove('Content-Length');
        $response->headers->set('Vary', trim($response->headers->get('Vary', '').', Accept-Language', ', '));
    }

    /**
     * A live language the visitor's browser prefers over English, unless
     * they've said no before.
     */
    protected function suggestion(Request $request): ?string
    {
        if ($request->cookie(Languages::DISMISSED_COOKIE) || $request->cookie(Languages::COOKIE) === Languages::SOURCE) {
            return null;
        }

        $live = Languages::liveCodes();
        $preferred = $request->getPreferredLanguage(array_merge([Languages::SOURCE], $live));
        $preferred = $preferred !== null ? strtolower(str_replace('_', '-', $preferred)) : null;

        foreach ($live as $code) {
            if ($preferred === $code || ($preferred !== null && explode('-', $preferred)[0] === $code)) {
                return $code;
            }
        }

        return null;
    }
}
