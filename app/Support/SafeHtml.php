<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The one place admin- or AI-authored HTML is made safe to print with
 * {!! !!}. Site pages can be written by department-scoped sub-admins and
 * by Tony (AI drafts from a free-text brief), and are previewed by the
 * super-admin on the same origin as the admin panel — so a stray
 * <script> or onerror= there is a session hijack, not a cosmetic bug.
 */
class SafeHtml
{
    protected static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        static::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                // No `style`: it would let an author lay an invisible
                // full-page link over a trusted page (click-jacking on our
                // own domain). Classes are enough for the editor's output.
                ->allowAttribute('class', '*')
                ->allowRelativeLinks()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeMedias()
                ->allowMediaSchemes(['https', 'http'])
                ->withMaxInputLength(500_000)
        );

        return static::$sanitizer->sanitize($html);
    }

    /**
     * A link target that can't execute script: a site-relative path
     * ("/pricing", not protocol-relative "//evil.test") or an http(s) URL.
     */
    public static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        // "/\\host" is normalised by browsers into a protocol-relative link
        // to another site, so a backslash after the slash is refused too.
        return (bool) preg_match('#^(/(?![/\\\\])|https?://)#i', $url);
    }
}
