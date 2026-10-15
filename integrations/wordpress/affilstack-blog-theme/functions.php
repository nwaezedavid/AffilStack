<?php

/**
 * AffilStack Blog theme.
 *
 * The header, footer, logo and colours come from the AffilStack website
 * (GET {AffilStack address}/api/blog/kit), cached for ten minutes, so
 * changes made in AffilStack's admin appear here automatically. Settings:
 * Appearance → AffilStack.
 */
defined('ABSPATH') || exit;

define('AFFILSTACK_THEME_VERSION', '1.0.0');

require_once __DIR__.'/inc/kit.php';
require_once __DIR__.'/inc/settings.php';
require_once __DIR__.'/inc/template-tags.php';
require_once __DIR__.'/inc/newsletter.php';
require_once __DIR__.'/inc/cta.php';
require_once __DIR__.'/inc/rank-math.php';

add_action(
    'after_setup_theme',
    function () {
        load_theme_textdomain('affilstack', get_template_directory().'/languages');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('automatic-feed-links');
        add_theme_support('responsive-embeds');
        add_theme_support('align-wide');
        add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
        add_image_size('affilstack-card', 800, 500, true);
        add_image_size('affilstack-hero', 1600, 900, true);
    }
);

add_action(
    'wp_enqueue_scripts',
    function () {
        wp_enqueue_style('affilstack', get_stylesheet_uri(), [], AFFILSTACK_THEME_VERSION);
        wp_add_inline_style('affilstack', affilstack_brand_css());
        wp_enqueue_script('affilstack', get_template_directory_uri().'/assets/js/theme.js', [], AFFILSTACK_THEME_VERSION, ['strategy' => 'defer', 'in_footer' => true]);

        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }
);

add_action(
    'wp_head',
    function () {
        $base = get_template_directory_uri().'/assets/fonts/';
        foreach (['space-grotesk-latin-600-normal', 'ibm-plex-sans-latin-400-normal'] as $font) {
            printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>'."\n", esc_url($base.$font.'.woff2'));
        }

        $favicon = affilstack_kit('site.favicon');
        if (! has_site_icon() && is_string($favicon) && $favicon !== '') {
            printf('<link rel="icon" href="%s">'."\n", esc_url($favicon));
        }
    },
    1
);

/**
 * The brand colours from AffilStack, as CSS custom properties.
 */
function affilstack_brand_css(): string
{
    $colors = (array) affilstack_kit('colors', []);
    $map = [
        'primary' => '--afs-brand',
        'navy' => '--afs-navy',
        'gold' => '--afs-gold',
    ];
    $css = '';

    foreach ($map as $key => $property) {
        $value = isset($colors[$key]) ? (string) $colors[$key] : '';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $css .= $property.':'.$value.';';
        }
    }

    return $css === '' ? '' : ':root{'.$css.'}';
}

/**
 * Reading time, e.g. "6 min read".
 */
function affilstack_reading_time(?int $post_id = null): string
{
    $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $post_id ?? get_the_ID())));
    $minutes = max(1, (int) ceil($words / 220));

    /* translators: %d: minutes */
    return sprintf(_n('%d min read', '%d min read', $minutes, 'affilstack'), $minutes);
}

/**
 * The post's first real category (not "Uncategorized"), or null.
 */
function affilstack_primary_category(?int $post_id = null): ?WP_Term
{
    foreach (get_the_category($post_id ?? get_the_ID()) as $category) {
        if ($category->slug !== 'uncategorized') {
            return $category;
        }
    }

    return null;
}

add_filter('excerpt_length', fn () => 26);
add_filter('excerpt_more', fn () => '…');
