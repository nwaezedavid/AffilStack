<?php

namespace App\Services\WordPress;

use App\Models\User;
use App\Models\WordpressSite;
use App\Support\OutboundUrlGuard;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Connecting WordPress sites through the AffilStack Connector plugin, the
 * OAuth-style way, so nobody copies keys around:
 *
 * 1. The plugin sends the customer's browser to /wordpress/connect with its
 *    site address, REST address, a return address on that same site and a
 *    random "state".
 * 2. The signed-in customer approves; startConnection() creates the site
 *    (status "pending") with a fresh secret and a one-time code that is
 *    handed back to the plugin through the browser.
 * 3. The plugin's server swaps the code for the secret over a direct HTTPS
 *    call (exchange()), so the secret never passes through the browser.
 *
 * How many sites an account may connect is set per plan
 * (plans.wordpress_sites_limit: 0 = not included, -1 = unlimited).
 */
class WordPressSiteService
{
    /**
     * The account that owns WordPress sites for this user (team seats share
     * their owner's sites).
     */
    public function owner(User $user): User
    {
        return $user->billableUser();
    }

    /**
     * 0 = not included, -1 = unlimited, otherwise the number of sites.
     */
    public function limitFor(User $user): int
    {
        $plan = $this->owner($user)->activeSubscription?->plan;

        return (int) ($plan?->wordpress_sites_limit ?? 0);
    }

    public function included(User $user): bool
    {
        return $this->limitFor($user) !== 0;
    }

    public function usedFor(User $user): int
    {
        return $this->owner($user)->wordpressSites()->whereIn('status', ['connected', 'pending'])->count();
    }

    public static function limitLabel(int $limit): string
    {
        return match (true) {
            $limit < 0 => 'Unlimited sites',
            $limit === 0 => 'Not included',
            $limit === 1 => '1 site',
            default => $limit.' sites',
        };
    }

    /**
     * Normalises and checks the addresses the plugin sent.
     *
     * @return array{site_url: string, rest_url: string, return_url: string}
     *
     * @throws InvalidArgumentException
     */
    public function validateRequest(string $siteUrl, string $restUrl, string $returnUrl): array
    {
        $siteUrl = rtrim(trim($siteUrl), '/');
        $restUrl = rtrim(trim($restUrl), '/').'/';
        $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));

        foreach (['site' => $siteUrl, 'REST API' => $restUrl, 'return' => $returnUrl] as $label => $url) {
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new InvalidArgumentException("The plugin sent an invalid {$label} address. Please update the AffilStack Connector plugin and try again.");
            }
        }

        // The code is handed back through the browser, so it may only go
        // back to the WordPress site that asked for it.
        if ($siteHost === '' || strtolower((string) parse_url($restUrl, PHP_URL_HOST)) !== $siteHost || strtolower((string) parse_url($returnUrl, PHP_URL_HOST)) !== $siteHost) {
            throw new InvalidArgumentException('The connection request doesn\'t match the site it came from. Start again from the plugin\'s settings page.');
        }

        if ($problem = OutboundUrlGuard::problem($restUrl)) {
            throw new InvalidArgumentException('AffilStack can\'t reach that site: '.$problem);
        }

        return ['site_url' => $siteUrl, 'rest_url' => $restUrl, 'return_url' => $returnUrl];
    }

    /**
     * Customer approved the site: returns the one-time code for the plugin.
     *
     * @throws InvalidArgumentException
     */
    public function startConnection(User $user, string $siteUrl, string $restUrl, ?string $siteName): string
    {
        $owner = $this->owner($user);
        $limit = $this->limitFor($user);
        $existing = $owner->wordpressSites()->where('site_url', $siteUrl)->first();

        if ($limit === 0) {
            throw new InvalidArgumentException('Your plan doesn\'t include the WordPress plugin. Upgrade your plan to connect WordPress sites.');
        }

        $countsAlready = $existing && in_array($existing->status, ['connected', 'pending'], true);

        if (! $countsAlready && $limit > 0 && $this->usedFor($user) >= $limit) {
            throw new InvalidArgumentException('Your plan allows '.static::limitLabel($limit).'. Disconnect a site or upgrade your plan to add another.');
        }

        $code = Str::random(48);
        $attributes = [
            'rest_url' => $restUrl,
            'name' => $siteName !== null && trim($siteName) !== '' ? Str::limit(trim($siteName), 120, '') : null,
            'secret' => Str::random(64),
            'status' => 'pending',
            'connect_code_hash' => hash('sha256', $code),
            'connect_code_expires_at' => now()->addMinutes((int) config('wordpress_plugin.connect_code_minutes', 10)),
            'last_error' => null,
        ];

        $existing
            ? $existing->update($attributes)
            : $owner->wordpressSites()->create($attributes + ['site_url' => $siteUrl]);

        return $code;
    }

    /**
     * The plugin collects its secret. Returns null for an unknown, used or
     * expired code.
     *
     * @param  array{site_url?: string, name?: string, wp_version?: string, plugin_version?: string, seo_plugin?: string}  $info
     */
    public function exchange(string $code, array $info): ?WordpressSite
    {
        $site = WordpressSite::query()
            ->where('connect_code_hash', hash('sha256', $code))
            ->where('connect_code_expires_at', '>', now())
            ->first();

        if (! $site || rtrim((string) ($info['site_url'] ?? ''), '/') !== $site->site_url) {
            return null;
        }

        $site->update([
            'status' => 'connected',
            'connect_code_hash' => null,
            'connect_code_expires_at' => null,
            'connected_at' => now(),
            'last_seen_at' => now(),
            'name' => isset($info['name']) && trim($info['name']) !== '' ? Str::limit(trim($info['name']), 120, '') : $site->name,
            'wp_version' => isset($info['wp_version']) ? Str::limit((string) $info['wp_version'], 20, '') : null,
            'plugin_version' => isset($info['plugin_version']) ? Str::limit((string) $info['plugin_version'], 20, '') : null,
            'seo_plugin' => isset($info['seo_plugin']) ? Str::limit((string) $info['seo_plugin'], 30, '') : null,
        ]);

        return $site;
    }

    public function disconnect(WordpressSite $site): void
    {
        $site->update([
            'status' => 'disconnected',
            'secret' => Str::random(64),
            'auto_send' => false,
            'connect_code_hash' => null,
            'connect_code_expires_at' => null,
        ]);
    }
}
