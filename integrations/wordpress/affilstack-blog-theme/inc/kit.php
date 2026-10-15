<?php

/**
 * The AffilStack kit: logo, colours, menu, footer, call to action and
 * newsletter settings from the main website, cached for ten minutes. A
 * copy of the last good kit is kept so the blog never loses its header if
 * AffilStack can't be reached.
 */
defined('ABSPATH') || exit;

const AFFILSTACK_KIT_TRANSIENT = 'affilstack_kit';
const AFFILSTACK_KIT_LAST_GOOD = 'affilstack_kit_last_good';

/**
 * The AffilStack website address, without a trailing slash.
 */
function affilstack_site_url(): string
{
    $url = trim((string) get_option('affilstack_site_url', 'https://affilstack.com'));

    return untrailingslashit($url !== '' ? $url : 'https://affilstack.com');
}

/**
 * The whole kit, or one value from it by dot path ("header.login.url").
 *
 * @param  string|null  $path  Dot path.
 * @param  mixed  $default  Returned when the value is missing.
 * @return mixed
 */
function affilstack_kit(?string $path = null, $default = null)
{
    static $kit = null;

    if ($kit === null) {
        $kit = affilstack_load_kit();
    }

    if ($path === null) {
        return $kit;
    }

    $value = $kit;
    foreach (explode('.', $path) as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * @return array<string, mixed>
 */
function affilstack_load_kit(): array
{
    $cached = get_transient(AFFILSTACK_KIT_TRANSIENT);
    if (is_array($cached)) {
        return $cached;
    }

    $kit = affilstack_fetch_kit();

    if (is_array($kit)) {
        set_transient(AFFILSTACK_KIT_TRANSIENT, $kit, 10 * MINUTE_IN_SECONDS);
        update_option(AFFILSTACK_KIT_LAST_GOOD, $kit, false);
        update_option('affilstack_kit_fetched_at', time(), false);
        delete_option('affilstack_kit_error');

        return $kit;
    }

    // AffilStack unreachable: keep the last good copy, retry in two minutes.
    $fallback = get_option(AFFILSTACK_KIT_LAST_GOOD);
    $fallback = is_array($fallback) ? $fallback : affilstack_default_kit();
    set_transient(AFFILSTACK_KIT_TRANSIENT, $fallback, 2 * MINUTE_IN_SECONDS);

    return $fallback;
}

/**
 * @return array<string, mixed>|null
 */
function affilstack_fetch_kit(): ?array
{
    $response = wp_remote_get(
        affilstack_site_url().'/api/blog/kit',
        [
            'timeout' => 6,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'AffilStack Blog theme/'.AFFILSTACK_THEME_VERSION.'; '.home_url(),
        ]
    );

    if (is_wp_error($response)) {
        update_option('affilstack_kit_error', $response->get_error_message(), false);

        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code !== 200 || ! is_array($data) || empty($data['site'])) {
        /* translators: %d: HTTP status */
        update_option('affilstack_kit_error', sprintf(__('AffilStack answered with HTTP %d.', 'affilstack'), $code), false);

        return null;
    }

    return $data;
}

function affilstack_flush_kit(): void
{
    delete_transient(AFFILSTACK_KIT_TRANSIENT);
}

/**
 * Used until AffilStack has been reached once.
 *
 * @return array<string, mixed>
 */
function affilstack_default_kit(): array
{
    $site = affilstack_site_url();

    return [
        'site' => ['name' => 'AffilStack', 'url' => $site],
        'colors' => [],
        'header' => [
            'tools_label' => 'Tools',
            'tools' => [],
            'all_features' => ['label' => 'All features', 'url' => $site.'/features'],
            'resources_label' => 'Resources',
            'resources' => [],
            'pricing' => ['label' => 'Pricing', 'url' => $site.'/pricing'],
            'expert' => ['label' => 'Talk To An Expert', 'url' => $site.'/contact'],
            'login' => ['label' => 'Login', 'url' => $site.'/login'],
            'cta' => ['label' => 'Get Started', 'url' => $site.'/pricing'],
        ],
        'footer' => ['tagline' => '', 'columns' => [], 'copyright' => '© '.gmdate('Y').' AffilStack.', 'note' => '', 'socials' => []],
        'cta_box' => ['enabled' => false],
        'newsletter' => ['enabled' => false],
    ];
}
