<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\SiteSetting;
use App\Support\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Sends posts written in AffilStack's admin to the WordPress blog through
 * WordPress's own REST API, signed in with a WordPress "application
 * password" (Users → Profile → Application Passwords) — no plugin needed.
 *
 * Creates the post the first time and updates the same post after that;
 * uploads the featured image when it changed; finds or creates categories
 * and tags by name; and fills Rank Math's SEO title, description and focus
 * keyword (the AffilStack theme exposes those fields to the REST API).
 *
 * Every request goes to the vetted IP of the blog's address and never
 * follows redirects, like BlogFeed.
 */
class WordPressPublisher
{
    public static function isConfigured(): bool
    {
        return BlogFeed::baseUrl() !== null
            && filled(SiteSetting::get('blog_wp_username'))
            && static::applicationPassword() !== null;
    }

    public static function applicationPassword(): ?string
    {
        $stored = SiteSetting::get('blog_wp_app_password');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function storeApplicationPassword(?string $password): void
    {
        $password = trim((string) $password);

        if ($password !== '') {
            SiteSetting::set('blog_wp_app_password', Crypt::encryptString($password));
        }
    }

    /**
     * Checks the address and login: "Connected as Nwaeze (can publish)".
     */
    public function test(): string
    {
        $me = $this->send('get', '/wp/v2/users/me', ['context' => 'edit']);
        $name = (string) ($me['name'] ?? $me['slug'] ?? 'your WordPress user');
        $canPublish = (bool) data_get($me, 'capabilities.publish_posts', false);

        if (! $canPublish) {
            throw new RuntimeException("Connected as {$name}, but that user can't publish posts. Use an Administrator or Editor account.");
        }

        return "Connected as {$name}, who can publish posts.";
    }

    /**
     * @param  string  $status  draft|publish
     */
    public function push(BlogPost $post, string $status): BlogPost
    {
        if (! in_array($status, ['draft', 'publish'], true)) {
            throw new InvalidArgumentException('Unknown status.');
        }

        try {
            $payload = [
                'title' => $post->title,
                'content' => (string) $post->content,
                'excerpt' => (string) $post->excerpt,
                'status' => $status,
                'categories' => $this->termIds('categories', (array) $post->categories),
                'tags' => $this->termIds('tags', (array) $post->tags),
                'meta' => array_filter([
                    'rank_math_title' => $post->seo_title,
                    'rank_math_description' => $post->seo_description,
                    'rank_math_focus_keyword' => $post->focus_keyword,
                ], fn ($value): bool => filled($value)),
            ];

            if (filled($post->slug)) {
                $payload['slug'] = Str::slug($post->slug);
            }

            if ($media = $this->featuredMediaId($post)) {
                $payload['featured_media'] = $media;
            }

            $result = $post->wp_post_id
                ? $this->send('post', '/wp/v2/posts/'.$post->wp_post_id, [], $payload)
                : $this->send('post', '/wp/v2/posts', [], $payload);

            $post->forceFill([
                'wp_post_id' => (int) $result['id'],
                'wp_status' => (string) ($result['status'] ?? $status),
                'wp_url' => is_string($result['link'] ?? null) ? $result['link'] : null,
                'synced_at' => now(),
                'last_error' => null,
            ])->save();

            return $post;
        } catch (Throwable $e) {
            $post->forceFill(['last_error' => Str::limit($e->getMessage(), 500)])->save();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<int, mixed>  $names
     * @return array<int, int>
     */
    protected function termIds(string $taxonomy, array $names): array
    {
        $ids = [];

        foreach (array_unique(array_filter(array_map(fn ($name): string => trim((string) $name), $names))) as $name) {
            $found = collect($this->send('get', '/wp/v2/'.$taxonomy, ['search' => $name, 'per_page' => 100]))
                ->first(fn ($term): bool => is_array($term) && Str::lower(html_entity_decode((string) ($term['name'] ?? ''))) === Str::lower($name));

            $ids[] = (int) ($found['id'] ?? $this->send('post', '/wp/v2/'.$taxonomy, [], ['name' => $name])['id']);
        }

        return $ids;
    }

    protected function featuredMediaId(BlogPost $post): ?int
    {
        $path = $post->featured_image_path;

        if (! filled($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if ($post->wp_featured_media_id && $post->wp_featured_image_path === $path) {
            return (int) $post->wp_featured_media_id;
        }

        $filename = Str::slug(Str::limit($post->title, 60, '')).'.'.pathinfo($path, PATHINFO_EXTENSION);
        $media = $this->send('post', '/wp/v2/media', [], null, [
            'body' => Storage::disk('public')->get($path),
            'type' => Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
            'filename' => $filename,
        ]);

        $post->forceFill(['wp_featured_media_id' => (int) $media['id'], 'wp_featured_image_path' => $path])->save();

        return (int) $media['id'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @param  array{body: string, type: string, filename: string}|null  $file
     * @return array<string|int, mixed>
     */
    protected function send(string $method, string $route, array $query = [], ?array $json = null, ?array $file = null): array
    {
        $base = BlogFeed::baseUrl();
        $username = (string) SiteSetting::get('blog_wp_username');
        $password = static::applicationPassword();

        if ($base === null || $username === '' || $password === null) {
            throw new RuntimeException('Add the blog address, WordPress username and application password in Admin → Website → Blog first.');
        }

        $url = $base.'/wp-json'.$route.($query ? '?'.http_build_query($query) : '');

        try {
            $target = OutboundUrlGuard::resolveSafeIp($url);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }

        $request = $this->client($target, $username, $password);

        try {
            $response = match (true) {
                $file !== null => $request
                    ->withHeaders(['Content-Disposition' => 'attachment; filename="'.$file['filename'].'"'])
                    ->withBody($file['body'], $file['type'])
                    ->post($url),
                $method === 'get' => $request->get($url),
                default => $request->post($url, $json ?? []),
            };
        } catch (Throwable $e) {
            throw new RuntimeException('The blog did not respond ('.Str::limit($e->getMessage(), 120).').');
        }

        return $this->decode($response);
    }

    /**
     * @param  array{host: string, port: int, ip: string}  $target
     */
    protected function client(array $target, string $username, string $password): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [
                "{$target['host']}:{$target['port']}:".(str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip']),
            ]]])
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AffilStack blog publisher']);
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        if ($response->successful() && is_array($json)) {
            return $json;
        }

        if ($response->redirect()) {
            throw new RuntimeException('The blog redirects to '.Str::limit((string) $response->header('Location'), 120).' — enter that address as the blog address instead.');
        }

        $message = is_array($json) ? strip_tags((string) ($json['message'] ?? '')) : '';

        throw new RuntimeException(match ($response->status()) {
            401, 403 => 'WordPress refused the login'.($message !== '' ? ': '.$message : '.').' Check the username and application password.',
            404 => 'The blog\'s REST API wasn\'t found. Is it WordPress, with pretty permalinks switched on (Settings → Permalinks → Post name)?',
            default => 'WordPress answered with HTTP '.$response->status().($message !== '' ? ': '.Str::limit($message, 200) : '.'),
        });
    }
}
