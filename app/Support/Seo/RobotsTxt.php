<?php

namespace App\Support\Seo;

use App\Models\SiteSetting;

/**
 * The live robots.txt (edited in SEO > SEO settings) plus a small,
 * spec-following evaluator (longest matching rule wins, Allow beats
 * Disallow on a tie, * and $ wildcards) used to warn the admin before a
 * rule blocks something search engines need — the homepage, the favicon,
 * /storage images or the built CSS/JS.
 */
final class RobotsTxt
{
    /**
     * Paths crawlers must always be able to fetch for the site to render and
     * show its icon in results.
     *
     * @var array<int, string>
     */
    public const ESSENTIAL_PATHS = ['/', '/favicon.ico', '/favicon-48x48.png', '/site.webmanifest', '/storage/', '/build/'];

    public static function defaultContent(): string
    {
        return "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";
    }

    /**
     * The stored robots.txt, always ending with a Sitemap line.
     */
    public static function content(?string $raw = null): string
    {
        $raw ??= SiteSetting::get('seo_robots_txt');
        $content = trim((string) ($raw ?: self::defaultContent()));

        if (! preg_match('/^\s*sitemap\s*:/im', $content)) {
            $content .= "\n\nSitemap: ".url('/sitemap.xml');
        }

        return $content."\n";
    }

    /**
     * Whether $agent may crawl $path under the given robots.txt content.
     */
    public static function isAllowed(string $content, string $path, string $agent = 'Googlebot'): bool
    {
        $rules = self::rulesFor($content, $agent);
        $best = null;

        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '') {
                continue;
            }

            if (! self::matches($pattern, $path)) {
                continue;
            }

            $length = strlen($pattern);

            if ($best === null || $length > $best[1] || ($length === $best[1] && $type === 'allow')) {
                $best = [$type, $length];
            }
        }

        return $best === null || $best[0] === 'allow';
    }

    /**
     * Essential paths the given content blocks for Googlebot or
     * Googlebot-Image.
     *
     * @return array<int, string>
     */
    public static function blockedEssentialPaths(string $content): array
    {
        return array_values(array_filter(
            self::ESSENTIAL_PATHS,
            fn (string $path) => ! self::isAllowed($content, $path, 'Googlebot') || ! self::isAllowed($content, $path, 'Googlebot-Image'),
        ));
    }

    /**
     * Rules from the most specific group matching $agent, falling back to
     * the "*" group — Google's own group-selection behaviour.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function rulesFor(string $content, string $agent): array
    {
        $groups = [];
        $currentAgents = [];
        $readingAgents = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $readingAgents) {
                    $currentAgents = [];
                }

                $currentAgents[] = strtolower($value);
                $readingAgents = true;

                continue;
            }

            if (in_array($field, ['allow', 'disallow'], true)) {
                $readingAgents = false;

                foreach ($currentAgents as $groupAgent) {
                    $groups[$groupAgent][] = [$field, $value];
                }
            }
        }

        $agent = strtolower($agent);
        $bestKey = null;

        foreach (array_keys($groups) as $groupAgent) {
            if ($groupAgent !== '*' && str_starts_with($agent, $groupAgent) && ($bestKey === null || strlen($groupAgent) > strlen($bestKey))) {
                $bestKey = $groupAgent;
            }
        }

        return $groups[$bestKey ?? '*'] ?? [];
    }

    protected static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = rtrim($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
