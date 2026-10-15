<?php

/**
 * Small rendering helpers: icons, the brand lockup and the card loop.
 */
defined('ABSPATH') || exit;

function affilstack_icon(string $name): void
{
    $paths = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'chevron' => '<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
        'chat' => '<path d="M7 9h10M7 13h6M5 19l2.5-3H18a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v12z" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linejoin="round"/>',
        'facebook' => '<path fill="currentColor" d="M14 8h3V4h-3c-2.8 0-4.5 1.9-4.5 4.6V11H7v4h2.5v9h4v-9h3l.5-4h-3.5V8.8c0-.5.3-.8.5-.8z"/>',
        'twitter' => '<path fill="currentColor" d="M17.8 3h3.1l-6.8 7.8 8 10.2h-6.3l-4.9-6.4L5.3 21H2.2l7.3-8.3L1.8 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/>',
        'linkedin' => '<path fill="currentColor" d="M4.98 3.5C4.98 4.88 3.87 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8h4V24h-4V8zm7.5 0h3.8v2.2h.1c.5-1 1.8-2.2 3.8-2.2 4 0 4.8 2.7 4.8 6.1V24h-4v-8.5c0-2 0-4.6-2.8-4.6s-3.2 2.2-3.2 4.4V24H8V8z"/>',
        'youtube' => '<path fill="currentColor" d="M23.5 6.2a3 3 0 00-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 00.5 6.2 31 31 0 000 12a31 31 0 00.5 5.8 3 3 0 002.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 002.1-2.1A31 31 0 0024 12a31 31 0 00-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/>',
        'instagram' => '<path fill="currentColor" d="M12 2.2c3.2 0 3.6 0 4.8.1 3.3.1 4.8 1.7 4.9 4.9.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 3.9 3.9 2.4 7.2 2.3 8.4 2.2 8.8 2.2 12 2.2zm0 4.9a4.9 4.9 0 100 9.8 4.9 4.9 0 000-9.8zm0 8.1a3.2 3.2 0 110-6.4 3.2 3.2 0 010 6.4zm5.1-9.5a1.2 1.2 0 100 2.3 1.2 1.2 0 000-2.3z"/>',
    ];

    if (isset($paths[$name])) {
        echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$paths[$name].'</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup above.
    }
}

/**
 * The logo (white version on dark backgrounds when there is one), or a
 * monogram and the site name.
 */
function affilstack_brand(bool $on_dark = false, bool $with_blog_label = false): void
{
    $name = (string) affilstack_kit('site.name', 'AffilStack');
    $logo = $on_dark ? (affilstack_kit('site.logo_white') ?: null) : (affilstack_kit('site.logo') ?: null);

    echo '<a class="afs-brand" href="'.esc_url((string) affilstack_kit('site.url', affilstack_site_url())).'">';

    if (is_string($logo) && $logo !== '') {
        echo '<img src="'.esc_url($logo).'" alt="'.esc_attr($name).'" height="32">';
    } else {
        $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2));
        echo '<span class="afs-brand__mark" aria-hidden="true">'.esc_html($initials).'</span>'.esc_html($name);
    }

    echo '</a>';

    if ($with_blog_label) {
        echo '<a class="afs-brand__blog" href="'.esc_url(home_url('/')).'">'.esc_html__('Blog', 'affilstack').'</a>';
    }
}

/**
 * One post card.
 */
function affilstack_card(bool $featured = false): void
{
    get_template_part('template-parts/card', null, ['featured' => $featured]);
}
