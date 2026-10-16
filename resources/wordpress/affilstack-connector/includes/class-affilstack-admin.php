<?php
/**
 * Settings → AffilStack: connect with one click, see recent imports,
 * disconnect.
 *
 * Connecting sends the administrator to AffilStack to approve the site.
 * AffilStack sends them back with a one-time code, which the plugin swaps
 * for this site's secret in a direct server-to-server call.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Admin {

	const PAGE      = 'affilstack-connector';
	const STATE_KEY = 'affilstack_connect_state_';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_affilstack_connect', array( __CLASS__, 'start_connect' ) );
		add_action( 'admin_post_affilstack_connect_return', array( __CLASS__, 'finish_connect' ) );
		add_action( 'admin_post_affilstack_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AFFILSTACK_CONNECTOR_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'AffilStack', 'affilstack-connector' ),
			__( 'AffilStack', 'affilstack-connector' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_style( 'affilstack-connector-admin', plugins_url( 'assets/admin.css', AFFILSTACK_CONNECTOR_FILE ), array(), AFFILSTACK_CONNECTOR_VERSION );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'affilstack-connector' ) . '</a>' );

		return $links;
	}

	/**
	 * A gentle nudge on the Plugins screen until the site is connected.
	 */
	public static function setup_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_options' ) || AffilStack_Connection::is_connected() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'AffilStack Connector is active.', 'affilstack-connector' ),
			esc_url( self::page_url() ),
			esc_html__( 'Connect it to your AffilStack account', 'affilstack-connector' )
		);
	}

	/**
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	public static function start_connect() {
		self::guard( 'affilstack_connect' );

		$state = wp_generate_password( 40, false, false );
		set_transient( self::STATE_KEY . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		// Built by hand: wp_nonce_url() HTML-escapes the "&", which would break the redirect back.
		$return_url = add_query_arg(
			array(
				'action'   => 'affilstack_connect_return',
				'_wpnonce' => wp_create_nonce( 'affilstack_connect_return' ),
			),
			admin_url( 'admin-post.php' )
		);

		$url = add_query_arg(
			array(
				'site_url'   => rawurlencode( untrailingslashit( home_url() ) ),
				'rest_url'   => rawurlencode( get_rest_url() ),
				'return_url' => rawurlencode( $return_url ),
				'site_name'  => rawurlencode( wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
				'state'      => $state,
			),
			AffilStack_Connection::base_url() . '/wordpress/connect'
		);

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- AffilStack lives on another domain.
		exit;
	}

	public static function finish_connect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'affilstack-connector' ), 403 );
		}

		check_admin_referer( 'affilstack_connect_return' );

		$expected = get_transient( self::STATE_KEY . get_current_user_id() );
		delete_transient( self::STATE_KEY . get_current_user_id() );

		$state = isset( $_GET['affilstack_state'] ) ? sanitize_text_field( wp_unslash( $_GET['affilstack_state'] ) ) : '';
		$code  = isset( $_GET['affilstack_code'] ) ? sanitize_text_field( wp_unslash( $_GET['affilstack_code'] ) ) : '';

		if ( ! $expected || ! hash_equals( (string) $expected, $state ) || '' === $code ) {
			self::back( 'error', __( 'The connection link expired. Please click "Connect to AffilStack" again.', 'affilstack-connector' ) );
		}

		$result = AffilStack_Connection::exchange( $code );

		if ( is_wp_error( $result ) ) {
			self::back( 'error', $result->get_error_message() );
		}

		AffilStack_Updater::clear_cache();

		self::back( 'connected', __( 'Connected. Articles you send from AffilStack will arrive here as drafts.', 'affilstack-connector' ) );
	}

	public static function disconnect() {
		self::guard( 'affilstack_disconnect' );

		// Tell AffilStack first (best effort), then forget the secret.
		AffilStack_Connection::request( 'POST', '/api/wordpress/disconnect', array( 'reason' => 'disconnected_in_wordpress' ) );
		AffilStack_Connection::forget();

		self::back( 'disconnected', __( 'Disconnected from AffilStack. Drafts already imported stay on this site.', 'affilstack-connector' ) );
	}

	protected static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'affilstack-connector' ), 403 );
		}

		check_admin_referer( $action );
	}

	protected static function back( $type, $message ) {
		set_transient( 'affilstack_notice_' . get_current_user_id(), array( $type, $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connection = AffilStack_Connection::get();
		$connected  = AffilStack_Connection::is_connected();
		$notice     = get_transient( 'affilstack_notice_' . get_current_user_id() );
		delete_transient( 'affilstack_notice_' . get_current_user_id() );

		$seo_labels = array(
			'yoast'     => 'Yoast SEO',
			'rank_math' => 'Rank Math',
			'aioseo'    => 'All in One SEO',
			'none'      => __( 'None detected (saved for Yoast SEO and Rank Math)', 'affilstack-connector' ),
		);
		$seo        = AffilStack_Connection::seo_plugin();
		?>
		<div class="wrap affilstack-wrap">
			<h1><?php esc_html_e( 'AffilStack', 'affilstack-connector' ); ?></h1>

			<?php if ( is_array( $notice ) && 2 === count( $notice ) ) : ?>
				<div class="notice <?php echo 'error' === $notice[0] ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>

			<div class="affilstack-card">
				<?php if ( $connected ) : ?>
					<p class="affilstack-status affilstack-status--on"><span class="affilstack-dot" aria-hidden="true"></span><?php esc_html_e( 'Connected', 'affilstack-connector' ); ?></p>
					<table class="affilstack-facts">
						<?php if ( ! empty( $connection['account_name'] ) ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'AffilStack account', 'affilstack-connector' ); ?></th><td><?php echo esc_html( $connection['account_name'] ); ?></td></tr>
						<?php endif; ?>
						<?php if ( ! empty( $connection['connected_by'] ) && get_userdata( (int) $connection['connected_by'] ) ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Drafts are written by', 'affilstack-connector' ); ?></th><td><?php echo esc_html( get_userdata( (int) $connection['connected_by'] )->display_name ); ?></td></tr>
						<?php endif; ?>
						<?php if ( ! empty( $connection['connected_at'] ) ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Connected on', 'affilstack-connector' ); ?></th><td><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $connection['connected_at'] ) ); ?></td></tr>
						<?php endif; ?>
						<tr><th scope="row"><?php esc_html_e( 'SEO plugin', 'affilstack-connector' ); ?></th><td><?php echo esc_html( $seo_labels[ $seo ] ); ?></td></tr>
					</table>

					<div class="affilstack-actions">
						<a class="button button-primary" href="<?php echo esc_url( ! empty( $connection['dashboard_url'] ) ? $connection['dashboard_url'] : AffilStack_Connection::base_url() . '/wordpress' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open AffilStack', 'affilstack-connector' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="affilstack-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect this site from AffilStack? Drafts already imported stay here.', 'affilstack-connector' ) ); ?>');">
							<input type="hidden" name="action" value="affilstack_disconnect">
							<?php wp_nonce_field( 'affilstack_disconnect' ); ?>
							<button type="submit" class="button-link affilstack-danger"><?php esc_html_e( 'Disconnect', 'affilstack-connector' ); ?></button>
						</form>
					</div>
				<?php else : ?>
					<p class="affilstack-status"><span class="affilstack-dot" aria-hidden="true"></span><?php esc_html_e( 'Not connected', 'affilstack-connector' ); ?></p>
					<p><?php esc_html_e( 'Connect this site to send your AffilStack blog articles here as drafts, with images, SEO fields, categories, tags, FAQ schema and your tracked affiliate links.', 'affilstack-connector' ); ?></p>
					<p><?php esc_html_e( 'You will be asked to sign in to AffilStack and approve this site. No keys to copy.', 'affilstack-connector' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="affilstack_connect">
						<?php wp_nonce_field( 'affilstack_connect' ); ?>
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Connect to AffilStack', 'affilstack-connector' ); ?></button>
					</form>
					<?php if ( ! is_ssl() && 0 !== strpos( home_url(), 'https://' ) ) : ?>
						<p class="description"><?php esc_html_e( 'Tip: this site is not using HTTPS. AffilStack can still connect, but HTTPS keeps the connection private.', 'affilstack-connector' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( $connected ) : ?>
				<?php self::render_recent(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	protected static function render_recent() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'publish', 'future', 'private' ),
				'meta_key'       => AffilStack_Importer::EXTERNAL_ID_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page' => 10,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		?>
		<h2><?php esc_html_e( 'Recent articles from AffilStack', 'affilstack-connector' ); ?></h2>
		<?php if ( ! $posts ) : ?>
			<p class="description"><?php esc_html_e( 'Nothing yet. Open a blog article in AffilStack and click "Send to WordPress".', 'affilstack-connector' ); ?></p>
		<?php else : ?>
			<table class="widefat striped affilstack-recent">
				<thead><tr><th><?php esc_html_e( 'Title', 'affilstack-connector' ); ?></th><th><?php esc_html_e( 'Status', 'affilstack-connector' ); ?></th><th><?php esc_html_e( 'Last updated', 'affilstack-connector' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $posts as $post ) : ?>
					<?php $status = get_post_status_object( $post->post_status ); ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
						<td><?php echo esc_html( $status ? $status->label : $post->post_status ); ?></td>
						<td><?php echo esc_html( get_the_modified_date( '', $post ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
