<?php

/**
 * Lets AffilStack fill Rank Math's SEO title, description and focus keyword
 * when it sends a post (WordPress REST API, signed in with an application
 * password). Only users who can edit posts may write these fields.
 */
defined('ABSPATH') || exit;

add_action(
    'init',
    function () {
        foreach (['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'] as $key) {
            register_post_meta(
                'post',
                $key,
                [
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback' => fn () => current_user_can('edit_posts'),
                ]
            );
        }
    },
    20
);
