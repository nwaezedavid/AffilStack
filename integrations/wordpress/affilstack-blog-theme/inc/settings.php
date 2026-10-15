<?php
/**
 * Appearance → AffilStack: the AffilStack address, the connection key for
 * newsletter sign-ups, and a "refresh now" for the header/footer.
 */
defined('ABSPATH') || exit;

add_action(
    'admin_menu',
    function () {
        add_theme_page(__('AffilStack', 'affilstack'), __('AffilStack', 'affilstack'), 'manage_options', 'affilstack', 'affilstack_render_settings');
    }
);

add_action(
    'admin_init',
    function () {
        register_setting(
            'affilstack',
            'affilstack_site_url',
            [
                'type' => 'string',
                'sanitize_callback' => function ($value) {
                    $value = esc_url_raw(trim((string) $value), ['https', 'http']);
                    affilstack_flush_kit();

                    return $value !== '' ? untrailingslashit($value) : 'https://affilstack.com';
                },
                'default' => 'https://affilstack.com',
            ]
        );
        register_setting(
            'affilstack',
            'affilstack_connection_key',
            [
                'type' => 'string',
                'sanitize_callback' => function ($value) {
                    $value = trim((string) $value);

                    // Blank keeps the saved key.
                    return $value === '' ? (string) get_option('affilstack_connection_key', '') : sanitize_text_field($value);
                },
                'default' => '',
            ]
        );
    }
);

add_action(
    'admin_post_affilstack_refresh_kit',
    function () {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'affilstack'), 403);
        }
        check_admin_referer('affilstack_refresh_kit');
        affilstack_flush_kit();
        affilstack_kit();
        wp_safe_redirect(admin_url('themes.php?page=affilstack&refreshed=1'));
        exit;
    }
);

function affilstack_render_settings(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $fetched = (int) get_option('affilstack_kit_fetched_at', 0);
    $error = (string) get_option('affilstack_kit_error', '');
    $has_key = (string) get_option('affilstack_connection_key', '') !== '';
    ?>
	<div class="wrap">
		<h1><?php esc_html_e('AffilStack', 'affilstack'); ?></h1>
		<p><?php esc_html_e('The blog\'s header, footer, logo, colours, call-to-action box and newsletter box come from your AffilStack website. Change them in AffilStack\'s admin under Website → Blog settings and Website → Navigation menu.', 'affilstack'); ?></p>

		<?php if (isset($_GET['refreshed'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended?>
			<div class="notice notice-success"><p><?php esc_html_e('Refreshed from AffilStack.', 'affilstack'); ?></p></div>
		<?php } ?>

		<form method="post" action="options.php">
			<?php settings_fields('affilstack'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="affilstack_site_url"><?php esc_html_e('AffilStack address', 'affilstack'); ?></label></th>
					<td><input type="url" class="regular-text" id="affilstack_site_url" name="affilstack_site_url" value="<?php echo esc_attr(affilstack_site_url()); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="affilstack_connection_key"><?php esc_html_e('Connection key', 'affilstack'); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="affilstack_connection_key" name="affilstack_connection_key" value="" autocomplete="off" placeholder="<?php echo $has_key ? esc_attr__('Saved — leave blank to keep', 'affilstack') : 'afsblog_…'; ?>">
						<p class="description"><?php esc_html_e('From AffilStack admin → Website → Blog settings → "Connection key". Needed for newsletter sign-ups.', 'affilstack'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e('Status', 'affilstack'); ?></h2>
		<p>
			<?php
            if ($fetched) {
                /* translators: %s: time ago */
                printf(esc_html__('Last updated from AffilStack %s ago.', 'affilstack'), esc_html(human_time_diff($fetched)));
            } else {
                esc_html_e('Not reached AffilStack yet.', 'affilstack');
            }
    ?>
		</p>
		<?php if ($error !== '') { ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html(sprintf( /* translators: %s: error */ __('Last attempt failed: %s The blog keeps using the last good copy.', 'affilstack'), $error)); ?></p></div>
		<?php } ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="affilstack_refresh_kit">
			<?php wp_nonce_field('affilstack_refresh_kit'); ?>
			<?php submit_button(__('Refresh header & footer now', 'affilstack'), 'secondary'); ?>
		</form>
	</div>
	<?php
}
