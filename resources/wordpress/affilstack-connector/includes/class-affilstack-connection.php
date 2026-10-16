<?php
/**
 * This site's link to an AffilStack account: stored in one option that is
 * never autoloaded, and the calls the plugin makes to AffilStack.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Connection {

	const OPTION = 'affilstack_connector_connection';

	/**
	 * @return array{site_id?: int, secret?: string, account_name?: string, connected_by?: int, connected_at?: int, dashboard_url?: string}
	 */
	public static function get() {
		$value = get_option( self::OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * @return bool
	 */
	public static function is_connected() {
		$connection = self::get();

		return ! empty( $connection['site_id'] ) && ! empty( $connection['secret'] );
	}

	public static function save( array $connection ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $connection, '', 'no' );
		} else {
			update_option( self::OPTION, $connection, false );
		}
	}

	public static function forget() {
		delete_option( self::OPTION );
		AffilStack_Updater::clear_cache();
	}

	/**
	 * @return string
	 */
	public static function base_url() {
		return untrailingslashit( (string) apply_filters( 'affilstack_connector_base_url', AFFILSTACK_CONNECTOR_BASE_URL ) );
	}

	/**
	 * Which SEO plugin fills the SEO fields.
	 *
	 * @return string yoast|rank_math|aioseo|none
	 */
	public static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}

		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rank_math';
		}

		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}

		return 'none';
	}

	/**
	 * @return array<string, string>
	 */
	public static function site_info() {
		return array(
			'site_url'       => untrailingslashit( home_url() ),
			'name'           => wp_strip_all_tags( get_bloginfo( 'name' ) ),
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => AFFILSTACK_CONNECTOR_VERSION,
			'seo_plugin'     => self::seo_plugin(),
		);
	}

	/**
	 * Swaps the one-time code from the connect screen for this site's secret.
	 *
	 * @return true|WP_Error
	 */
	public static function exchange( $code ) {
		$response = wp_remote_post(
			self::base_url() . '/api/wordpress/exchange',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array_merge( array( 'code' => $code ), self::site_info() ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'affilstack_unreachable', __( 'Could not reach AffilStack. Please try again in a moment.', 'affilstack-connector' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $data['site_id'] ) || empty( $data['secret'] ) ) {
			$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : __( 'AffilStack did not accept the connection. Please click "Connect to AffilStack" again.', 'affilstack-connector' );

			return new WP_Error( 'affilstack_rejected', $message );
		}

		self::save(
			array(
				'site_id'       => (int) $data['site_id'],
				'secret'        => (string) $data['secret'],
				'account_name'  => isset( $data['account_name'] ) ? sanitize_text_field( $data['account_name'] ) : '',
				'dashboard_url' => isset( $data['dashboard_url'] ) ? esc_url_raw( $data['dashboard_url'] ) : self::base_url() . '/wordpress',
				'connected_by'  => get_current_user_id(),
				'connected_at'  => time(),
			)
		);

		return true;
	}

	/**
	 * A signed call to AffilStack.
	 *
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function request( $method, $path, $body = null ) {
		$connection = self::get();

		if ( empty( $connection['site_id'] ) || empty( $connection['secret'] ) ) {
			return new WP_Error( 'affilstack_not_connected', __( 'This site is not connected to AffilStack.', 'affilstack-connector' ) );
		}

		$raw     = null === $body ? '' : wp_json_encode( $body );
		$headers = array_merge(
			array(
				'Accept'                       => 'application/json',
				'X-AffilStack-Plugin-Version' => AFFILSTACK_CONNECTOR_VERSION,
			),
			AffilStack_Signature::headers( $connection['site_id'], $connection['secret'], $method, $path, $raw )
		);

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request(
			self::base_url() . $path,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => $headers,
				'body'    => null === $body ? null : $raw,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'affilstack_http_' . $code, is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : 'AffilStack returned HTTP ' . $code );
		}

		return $data;
	}
}
