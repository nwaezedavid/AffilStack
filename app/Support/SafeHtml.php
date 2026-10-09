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

        return static::stripOverlayClasses(static::$sanitizer->sanitize($html));
    }

    /**
     * Classes are allowed for the editor's styling, but Tailwind utilities
     * that take an element out of the flow, stack it above the page or make
     * it invisible would rebuild the full-page invisible link that removing
     * `style` was meant to stop — those classes are dropped.
     */
    protected static function stripOverlayClasses(string $html): string
    {
        return (string) preg_replace_callback('/\sclass="([^"]*)"/i', function (array $match): string {
            $kept = array_filter(preg_split('/\s+/', $match[1]) ?: [], function (string $class): bool {
                // Judge the utility after its last variant prefix (md:, *:,
                // [&]:, group-hover/x:, data-[x]: …) and any ! or - sign.
                $base = preg_replace('/^!?-?/', '', (string) preg_replace('/^.*:/', '', $class));

                return $base !== '' && ! preg_match(
                    '/^(fixed|absolute|sticky|inset(-.*)?|top-.*|right-.*|bottom-.*|left-.*|start-.*|end-.*|z-.*|opacity-.*|invisible|h-screen|w-screen|min-h-screen|min-w-screen|h-dvh|w-dvw|pointer-events-.*|\[.*\]|[a-z-]+-\[.*\])$/i',
                    $base
                );
            });

            return $kept === [] ? '' : ' class="'.implode(' ', $kept).'"';
        }, $html);
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
