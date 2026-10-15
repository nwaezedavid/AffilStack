<?php
/**
 * Newsletter box: shown after every post (and via [affilstack_newsletter]).
 * The form posts here; the theme forwards the sign-up to AffilStack with
 * the connection key, AffilStack emails a confirmation link, and the
 * reader joins the CRM list once they click it.
 */
defined('ABSPATH') || exit;

function affilstack_newsletter_enabled(): bool
{
    return (bool) affilstack_kit('newsletter.enabled', false) && (string) get_option('affilstack_connection_key', '') !== '';
}

function affilstack_newsletter_form(): string
{
    if (! affilstack_newsletter_enabled()) {
        return '';
    }

    $heading = (string) affilstack_kit('newsletter.heading', '');
    $text = (string) affilstack_kit('newsletter.text', '');
    $button = (string) affilstack_kit('newsletter.button_label', __('Subscribe', 'affilstack'));
    $privacy = (string) affilstack_kit('newsletter.privacy_url', '');
    $back = is_singular() ? get_permalink() : home_url(add_query_arg([]));

    ob_start();
    ?>
	<section class="afs-newsletter" aria-labelledby="afs-newsletter-heading">
		<h2 id="afs-newsletter-heading"><?php echo esc_html($heading); ?></h2>
		<?php if ($text !== '') { ?>
			<p><?php echo esc_html($text); ?></p>
		<?php } ?>
		<form class="afs-newsletter__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="affilstack_subscribe">
			<input type="hidden" name="back" value="<?php echo esc_url($back); ?>">
			<?php wp_nonce_field('affilstack_subscribe', 'afs_nonce'); ?>
			<label class="afs-sr" for="afs-email"><?php esc_html_e('Email address', 'affilstack'); ?></label>
			<input type="email" id="afs-email" name="email" required maxlength="255" placeholder="<?php esc_attr_e('you@example.com', 'affilstack'); ?>" autocomplete="email">
			<label class="afs-hp" aria-hidden="true"><?php esc_html_e('Leave this empty', 'affilstack'); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			<button type="submit" class="afs-btn afs-btn--navy"><?php echo esc_html($button); ?></button>
		</form>
		<p class="afs-newsletter__fine">
			<?php esc_html_e('We\'ll email you a link to confirm. Unsubscribe any time.', 'affilstack'); ?>
			<?php if ($privacy !== '') { ?>
				<a href="<?php echo esc_url($privacy); ?>"><?php esc_html_e('Privacy policy', 'affilstack'); ?></a>
			<?php } ?>
		</p>
	</section>
	<?php
    return (string) ob_get_clean();
}

add_shortcode('affilstack_newsletter', 'affilstack_newsletter_form');

function affilstack_handle_subscribe(): void
{
    $back = isset($_POST['back']) ? esc_url_raw(wp_unslash($_POST['back'])) : home_url('/');
    $back = wp_validate_redirect($back, home_url('/'));

    $result = 'error';

    if (isset($_POST['afs_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['afs_nonce'])), 'affilstack_subscribe') && affilstack_newsletter_enabled()) {
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $trapped = ! empty($_POST['website']);

        if ($trapped) {
            $result = 'sent'; // Bots get the same answer and nothing happens.
        } elseif (is_email($email) && affilstack_rate_limit_ok()) {
            $response = wp_remote_post(
                affilstack_site_url().'/api/blog/subscribe',
                [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json',
                        'X-AffilStack-Blog-Key' => (string) get_option('affilstack_connection_key', ''),
                    ],
                    'body' => [
                        'email' => $email,
                        'source_url' => $back,
                    ],
                ]
            );

            $result = (! is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) ? 'sent' : 'error';
        }
    }

    wp_safe_redirect(add_query_arg('afs_newsletter', $result, $back).'#afs-newsletter-heading');
    exit;
}

add_action('admin_post_nopriv_affilstack_subscribe', 'affilstack_handle_subscribe');
add_action('admin_post_affilstack_subscribe', 'affilstack_handle_subscribe');

/**
 * Five sign-up attempts per visitor IP per ten minutes.
 */
function affilstack_rate_limit_ok(): bool
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key = 'afs_nl_'.md5($ip);
    $n = (int) get_transient($key);

    if ($n >= 5) {
        return false;
    }

    set_transient($key, $n + 1, 10 * MINUTE_IN_SECONDS);

    return true;
}

/**
 * Notices after signing up and after confirming (AffilStack sends readers
 * back with ?subscribed=1).
 */
function affilstack_newsletter_notice(): void
{
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
    $state = isset($_GET['afs_newsletter']) ? sanitize_key(wp_unslash($_GET['afs_newsletter'])) : '';

    if (isset($_GET['subscribed'])) {
        $state = 'confirmed';
    }
    // phpcs:enable

    $messages = [
        'sent' => ['', __('Almost done — check your inbox and click the link to confirm your subscription.', 'affilstack')],
        'confirmed' => ['', __('You\'re subscribed. Thanks for joining!', 'affilstack')],
        'error' => ['afs-notice--error', __('Sorry, that didn\'t work. Please check your email address and try again in a minute.', 'affilstack')],
    ];

    if (isset($messages[$state])) {
        printf('<div class="afs-notice %s" role="status"><p>%s</p></div>', esc_attr($messages[$state][0]), esc_html($messages[$state][1]));
    }
}
