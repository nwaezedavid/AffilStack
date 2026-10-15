<?php
/**
 * Comments.
 */
defined('ABSPATH') || exit;

if (post_password_required()) {
    return;
}

if (have_comments()) {
    ?>
	<h2><?php echo esc_html(sprintf( /* translators: %s: number of comments */ _n('%s comment', '%s comments', get_comments_number(), 'affilstack'), number_format_i18n(get_comments_number()))); ?></h2>
	<ol class="comment-list"><?php wp_list_comments(['style' => 'ol', 'short_ping' => true, 'avatar_size' => 40]); ?></ol>
	<?php
    the_comments_navigation();
}

comment_form();
