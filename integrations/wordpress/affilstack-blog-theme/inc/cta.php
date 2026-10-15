<?php
/**
 * The call-to-action box at the end of every post. Its words and link are
 * edited in AffilStack admin → Website → Blog settings. A post can hide it
 * with the custom field affilstack_hide_cta = 1.
 */
defined('ABSPATH') || exit;

function affilstack_cta_box(?int $post_id = null): string
{
    if (! affilstack_kit('cta_box.enabled', false)) {
        return '';
    }

    if ($post_id && get_post_meta($post_id, 'affilstack_hide_cta', true)) {
        return '';
    }

    $heading = (string) affilstack_kit('cta_box.heading', '');
    $text = (string) affilstack_kit('cta_box.text', '');
    $label = (string) affilstack_kit('cta_box.button_label', '');
    $url = (string) affilstack_kit('cta_box.button_url', '');

    if ($heading === '' || $url === '') {
        return '';
    }

    ob_start();
    ?>
	<aside class="afs-cta" aria-label="<?php echo esc_attr((string) affilstack_kit('site.name', 'AffilStack')); ?>">
		<span class="afs-eyebrow"><?php echo esc_html((string) affilstack_kit('site.name', 'AffilStack')); ?></span>
		<h2><?php echo esc_html($heading); ?></h2>
		<?php if ($text !== '') { ?>
			<p><?php echo esc_html($text); ?></p>
		<?php } ?>
		<a class="afs-btn afs-btn--gold" href="<?php echo esc_url($url); ?>">
			<?php echo esc_html($label !== '' ? $label : __('Learn more', 'affilstack')); ?>
			<?php affilstack_icon('arrow'); ?>
		</a>
	</aside>
	<?php
    return (string) ob_get_clean();
}
