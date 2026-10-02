<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Cache;

/**
 * Remembers what a route-based public page last rendered by default — its
 * built-in title, description and visible text — so the SEO editor can
 * preview, score and keyword-check the page exactly as visitors see it
 * without re-rendering it inside the admin request. Written by
 * SeoHeadBuilder only when something changed (a hash compare, not a write
 * per page view).
 */
final class RenderedPageSnapshot
{
    protected const MAX_TEXT = 30000;

    public static function remember(string $routeName, string $title, string $description, string $bodyHtml): void
    {
        if (! PublicPages::has($routeName)) {
            return;
        }

        $text = self::plainText($bodyHtml);
        $snapshot = ['title' => $title, 'description' => $description, 'text' => $text];
        $hash = md5((string) json_encode($snapshot));
        $key = "seo_snapshot:{$routeName}";

        if ((Cache::get($key)['hash'] ?? null) === $hash) {
            return;
        }

        Cache::forever($key, $snapshot + ['hash' => $hash, 'captured_at' => now()->toIso8601String()]);
    }

    /**
     * @return array{title: string, description: string, text: string, captured_at?: string}|null
     */
    public static function get(string $routeName): ?array
    {
        $snapshot = Cache::get("seo_snapshot:{$routeName}");

        return is_array($snapshot) ? $snapshot : null;
    }

    public static function plainText(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, self::MAX_TEXT);
    }
}
