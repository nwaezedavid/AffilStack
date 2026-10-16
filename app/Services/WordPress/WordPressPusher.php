<?php

namespace App\Services\WordPress;

use App\Models\WordpressDelivery;
use App\Models\WordpressSite;
use App\Support\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Talks to the AffilStack Connector plugin on a customer's WordPress site:
 * every request is signed (ConnectorSignature) and goes through the
 * outbound URL guard, pinned to the address it checked, so a site URL can
 * never be pointed at AffilStack's own network.
 *
 * Endpoints on the WordPress side (wp-json/affilstack/v1/…):
 * - POST posts       create or update the draft for an article
 * - GET  status      site name, versions, SEO plugin
 * - POST disconnect  forget this AffilStack account
 */
class WordPressPusher
{
    public function __construct(protected ArticlePayload $payloads) {}

    /**
     * Sends one article. Leaves the delivery "sent" (with the draft's edit
     * link) or "failed" (with a reason the customer can act on).
     */
    public function deliver(WordpressDelivery $delivery): void
    {
        $site = $delivery->site;

        if (! $site || ! $site->isConnected()) {
            $delivery->update(['status' => 'failed', 'error' => 'This WordPress site is no longer connected. Reconnect it from the plugin\'s settings page.']);

            return;
        }

        try {
            $result = $this->request($site, 'POST', 'affilstack/v1/posts', $this->payloads->build($delivery->generation));
        } catch (ArticleAlreadyPublished $e) {
            // Published, scheduled or trashed in WordPress: the plugin leaves
            // it alone, and the delivery shows where it stands.
            $delivery->update([
                'status' => 'sent',
                'wp_post_id' => $e->postId ?? $delivery->wp_post_id,
                'wp_post_status' => $e->postStatus ?? 'publish',
                'edit_url' => $this->sameSite($site, $e->editUrl),
                'error' => null,
            ]);
            $site->update(['last_seen_at' => now(), 'last_error' => null]);

            return;
        } catch (RuntimeException $e) {
            $delivery->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $site->update(['last_error' => $e->getMessage()]);

            return;
        }

        $delivery->update([
            'status' => 'sent',
            'wp_post_id' => isset($result['post_id']) ? (int) $result['post_id'] : null,
            'wp_post_status' => isset($result['status']) ? Str::limit((string) $result['status'], 20, '') : 'draft',
            'edit_url' => $this->sameSite($site, $result['edit_url'] ?? null),
            'preview_url' => $this->sameSite($site, $result['preview_url'] ?? null),
            'error' => null,
            'sent_at' => now(),
        ]);

        $site->update(['last_seen_at' => now(), 'last_error' => null]);
    }

    /**
     * Checks the connection and refreshes the site's details.
     *
     * @throws RuntimeException
     */
    public function status(WordpressSite $site): array
    {
        $result = $this->request($site, 'GET', 'affilstack/v1/status');

        $site->update(array_filter([
            'name' => isset($result['name']) ? Str::limit((string) $result['name'], 120, '') : null,
            'wp_version' => isset($result['wp_version']) ? Str::limit((string) $result['wp_version'], 20, '') : null,
            'plugin_version' => isset($result['plugin_version']) ? Str::limit((string) $result['plugin_version'], 20, '') : null,
            'seo_plugin' => isset($result['seo_plugin']) ? Str::limit((string) $result['seo_plugin'], 30, '') : null,
            'last_seen_at' => now(),
        ]) + ['last_error' => null]);

        return $result;
    }

    /**
     * Tells the plugin it has been disconnected. Best effort: the site may
     * already be gone.
     */
    public function notifyDisconnect(WordpressSite $site): void
    {
        try {
            $this->request($site, 'POST', 'affilstack/v1/disconnect', []);
        } catch (Throwable) {
            // The secret is rotated anyway, so the old connection is dead.
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function request(WordpressSite $site, string $method, string $route, ?array $json = null): array
    {
        $url = rtrim($site->rest_url, '/').'/'.ltrim($route, '/');
        $body = $method === 'GET' ? '' : (string) json_encode($json ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $target = OutboundUrlGuard::resolveSafeIp($url);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('AffilStack can\'t reach this site: '.$e->getMessage());
        }

        // The plugin checks the signature against its REST route
        // ("/affilstack/v1/posts"), which is the same whether the site
        // uses pretty permalinks (/wp-json/…) or ?rest_route=.
        $headers = ConnectorSignature::headers($site->id, (string) $site->secret, $method, '/'.ltrim($route, '/'), $body);

        try {
            $response = $method === 'GET'
                ? $this->client($target)->withHeaders($headers)->get($url)
                : $this->client($target)->withHeaders($headers)->withBody($body, 'application/json')->post($url);
        } catch (Throwable $e) {
            throw new RuntimeException('Your WordPress site didn\'t respond ('.Str::limit($e->getMessage(), 120).'). Check that it\'s online and try again.');
        }

        return $this->decode($response);
    }

    /**
     * @param  array{host: string, port: int, ip: string}  $target
     */
    protected function client(array $target): PendingRequest
    {
        return Http::timeout(60)
            ->connectTimeout(8)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [
                "{$target['host']}:{$target['port']}:".(str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip']),
            ]]])
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AffilStack WordPress connector']);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        if ($response->successful() && is_array($json)) {
            return $json;
        }

        $code = is_array($json) ? (string) ($json['code'] ?? '') : '';
        $message = is_array($json) ? (string) ($json['message'] ?? '') : '';

        if ($code === 'affilstack_published') {
            $data = is_array($json['data'] ?? null) ? $json['data'] : [];

            throw new ArticleAlreadyPublished(
                isset($data['post_id']) ? (int) $data['post_id'] : null,
                isset($data['post_status']) ? Str::limit((string) $data['post_status'], 20, '') : null,
                isset($data['edit_url']) && is_string($data['edit_url']) ? $data['edit_url'] : null,
            );
        }

        throw new RuntimeException(match (true) {
            $code === 'affilstack_not_connected', $response->status() === 401 => 'Your WordPress site no longer recognises this connection. Reconnect it from Settings → AffilStack in WordPress.',
            $response->status() === 404 => 'The AffilStack Connector plugin isn\'t active on this site (or pretty permalinks are off). Activate the plugin and try again.',
            $response->redirect() => 'Your site redirected the request. Make sure the site address in WordPress (Settings → General) uses its final https:// address.',
            $response->status() === 403 => 'Your site or its security plugin blocked AffilStack. Allow requests to /wp-json/affilstack/ and try again.',
            $message !== '' => 'WordPress said: '.Str::limit(strip_tags($message), 200),
            default => 'Your WordPress site returned an error (HTTP '.$response->status().'). Try again in a moment.',
        });
    }

    /**
     * Only links back to the same WordPress site are kept.
     */
    protected function sameSite(WordpressSite $site, mixed $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return strtolower((string) parse_url($url, PHP_URL_HOST)) === strtolower($site->host()) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ? Str::limit($url, 250, '')
            : null;
    }
}
