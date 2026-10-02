<?php

namespace App\Services\Blog;

use App\Models\SiteSetting;
use App\Support\OutboundUrlGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The homepage's "latest articles" section, pulled live from the WordPress
 * blog (e.g. https://blog.affilstack.com) through the standard WP REST API
 * — /wp-json/wp/v2/posts?per_page=N&_embed — so a post published on the
 * blog shows up on the homepage within the hour with nothing to copy over.
 *
 * Never allowed to break or slow the homepage: results are cached for an
 * hour, a failure (blog down, not WordPress, bad URL) is logged as a
 * warning and cached as "no posts" for ten minutes — the section simply
 * hides — and every title/excerpt is reduced to plain text before it
 * reaches a template, since WordPress returns them as rendered HTML.
 *
 * Settings (Content → Homepage → Blog tab): blog_feed_enabled,
 * blog_feed_url (falls back to the SEO "Blog URL"), blog_feed_title,
 * blog_feed_subtitle, blog_feed_count (3–6).
 */
class BlogFeed
{
    public const CACHE_KEY = 'blog_feed:posts';

    public const CACHE_SECONDS = 3600;

    public const FAILURE_CACHE_SECONDS = 600;

    public const DEFAULT_TITLE = 'Fresh from the blog';

    public const DEFAULT_SUBTITLE = 'Playbooks, product research and affiliate strategies you can use this week.';

    public static function isEnabled(): bool
    {
        return SiteSetting::flag('blog_feed_enabled', true);
    }

    /**
     * The blog's address — the feed's own setting, or the SEO settings'
     * "Blog URL" when that's the only one filled in — normalised to a bare
     * site root (no trailing slash, no /wp-json suffix), or null.
     */
    public static function baseUrl(): ?string
    {
        return static::normalizeBaseUrl(SiteSetting::get('blog_feed_url') ?: SiteSetting::get('seo_blog_url'));
    }

    public static function normalizeBaseUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $url = (string) preg_replace('#[?\#].*$#', '', $url);
        $url = (string) preg_replace('#/wp-json(/.*)?$#i', '', $url);
        $url = rtrim($url, '/');

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public static function postCount(): int
    {
        return max(3, min(6, (int) (SiteSetting::get('blog_feed_count') ?: 3)));
    }

    public static function title(): string
    {
        return trim((string) SiteSetting::get('blog_feed_title', '')) ?: static::DEFAULT_TITLE;
    }

    public static function subtitle(): string
    {
        return trim((string) SiteSetting::get('blog_feed_subtitle', '')) ?: static::DEFAULT_SUBTITLE;
    }

    /**
     * The latest posts for the homepage — cached, and an empty array
     * whenever the feed is off, not configured, or the blog can't be read.
     *
     * @return array<int, array{title: string, excerpt: string, url: string, image: ?string, image_alt: string, date: ?string, author: ?string, category: ?string}>
     */
    public function latest(): array
    {
        $baseUrl = static::baseUrl();

        if (! static::isEnabled() || ! $baseUrl) {
            return [];
        }

        $count = static::postCount();
        $signature = $baseUrl.'|'.$count;
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['signature'] ?? null) === $signature && isset($cached['posts'])) {
            return $cached['posts'];
        }

        try {
            $posts = $this->fetch($baseUrl, $count);
            Cache::put(self::CACHE_KEY, ['signature' => $signature, 'posts' => $posts], self::CACHE_SECONDS);

            return $posts;
        } catch (Throwable $e) {
            Log::warning('Homepage blog feed unavailable — the latest-articles section is hidden until it recovers.', [
                'blog_url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);

            Cache::put(self::CACHE_KEY, ['signature' => $signature, 'posts' => []], self::FAILURE_CACHE_SECONDS);

            return [];
        }
    }

    /**
     * The admin's "Test blog connection" button — always a fresh request,
     * never the cache, and a plain-English result either way.
     *
     * @return array{ok: bool, count: int, message: string, posts: array<int, array<string, mixed>>}
     */
    public function test(?string $url = null): array
    {
        $baseUrl = static::normalizeBaseUrl($url ?? (SiteSetting::get('blog_feed_url') ?: SiteSetting::get('seo_blog_url')));

        if (! $baseUrl) {
            return ['ok' => false, 'count' => 0, 'posts' => [], 'message' => 'Enter your blog address first, e.g. https://blog.affilstack.com'];
        }

        try {
            $posts = $this->fetch($baseUrl, static::postCount());
        } catch (Throwable $e) {
            return ['ok' => false, 'count' => 0, 'posts' => [], 'message' => 'Could not read posts from '.$baseUrl.': '.$e->getMessage()];
        }

        if ($posts === []) {
            return ['ok' => true, 'count' => 0, 'posts' => [], 'message' => 'Connected to '.$baseUrl.', but it has no published posts yet — the section stays hidden until it does.'];
        }

        return [
            'ok' => true,
            'count' => count($posts),
            'posts' => $posts,
            'message' => 'Connected to '.$baseUrl.' — found '.count($posts).' '.Str::plural('post', count($posts)).'. Latest: "'.$posts[0]['title'].'".',
        ];
    }

    /**
     * Forgets the cached posts so the next homepage view refetches — called
     * whenever the blog settings are saved.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{title: string, excerpt: string, url: string, image: ?string, image_alt: string, date: ?string, author: ?string, category: ?string}>
     *
     * @throws RuntimeException when the blog can't be reached or isn't WordPress
     */
    public function fetch(string $baseUrl, int $count): array
    {
        $endpoints = [
            $baseUrl.'/wp-json/wp/v2/posts?per_page='.$count.'&_embed=1',
            // Sites without "pretty permalinks" only answer on ?rest_route=.
            $baseUrl.'/?rest_route=/wp/v2/posts&per_page='.$count.'&_embed=1',
        ];

        $lastError = 'no response';

        foreach ($endpoints as $endpoint) {
            if ($problem = OutboundUrlGuard::problem($endpoint)) {
                throw new RuntimeException($problem);
            }

            try {
                $response = Http::timeout(5)
                    ->connectTimeout(3)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'AffilStack homepage blog feed'])
                    ->get($endpoint);
            } catch (Throwable $e) {
                throw new RuntimeException('the blog did not respond ('.Str::limit($e->getMessage(), 120).')');
            }

            $rows = $this->postsFrom($response);

            if ($rows !== null) {
                return array_slice(array_values(array_filter(array_map(
                    fn (mixed $row): ?array => is_array($row) ? $this->normalizePost($row, $baseUrl) : null,
                    $rows,
                ))), 0, $count);
            }

            $lastError = $response->successful()
                ? 'the address answered, but not with WordPress posts — is it a WordPress site with the REST API enabled?'
                : 'the blog answered with HTTP '.$response->status();
        }

        throw new RuntimeException($lastError);
    }

    /**
     * @return array<int, mixed>|null the decoded post list, or null when this isn't one
     */
    protected function postsFrom(Response $response): ?array
    {
        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) && array_is_list($json) ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array{title: string, excerpt: string, url: string, image: ?string, image_alt: string, date: ?string, author: ?string, category: ?string}|null
     */
    protected function normalizePost(array $post, string $baseUrl): ?array
    {
        $title = static::plainText(data_get($post, 'title.rendered'), 140);

        if ($title === '') {
            return null;
        }

        $link = (string) data_get($post, 'link', '');
        $image = data_get($post, '_embedded.wp:featuredmedia.0.media_details.sizes.medium_large.source_url')
            ?: data_get($post, '_embedded.wp:featuredmedia.0.source_url');

        $category = collect((array) data_get($post, '_embedded.wp:term', []))
            ->flatten(1)
            ->first(fn ($term) => is_array($term) && ($term['taxonomy'] ?? null) === 'category' && ($term['slug'] ?? null) !== 'uncategorized');

        $date = data_get($post, 'date_gmt') ?: data_get($post, 'date');

        return [
            'title' => $title,
            'excerpt' => static::plainText(data_get($post, 'excerpt.rendered'), 170),
            'url' => static::isHttpUrl($link) ? $link : $baseUrl,
            'image' => is_string($image) && static::isHttpUrl($image) ? $image : null,
            'image_alt' => static::plainText(data_get($post, '_embedded.wp:featuredmedia.0.alt_text'), 140) ?: $title,
            'date' => is_string($date) && strtotime($date) !== false ? date(DATE_ATOM, (int) strtotime($date)) : null,
            'author' => static::plainText(data_get($post, '_embedded.author.0.name'), 60) ?: null,
            'category' => is_array($category) ? (static::plainText($category['name'] ?? '', 40) ?: null) : null,
        ];
    }

    /**
     * WordPress hands back titles and excerpts as rendered HTML, complete
     * with entities (&#8217;), tags, and a trailing "[&hellip;]" or
     * "Continue reading" link. This turns that into a clean sentence.
     */
    public static function plainText(mixed $html, int $limit): string
    {
        $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = trim((string) preg_replace('/\s*(\[(…|\.\.\.)\]|(Continue reading|Read more)\b.*)$/iu', '', $text));

        return Str::limit($text, $limit, '…', preserveWords: true);
    }

    protected static function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }
}
