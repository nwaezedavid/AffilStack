<?php
/**
 * Updates from AffilStack: connected sites see new versions on the Plugins
 * screen and update with one click, like any plugin.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Updater {

	const CACHE = 'affilstack_connector_update_info';
	const SLUG  = 'affilstack-connector';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_details' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 0 );
	}

	public static function clear_cache() {
		delete_site_transient( self::CACHE );
	}

	/**
	 * @return array|null
	 */
	public static function info() {
		if ( ! AffilStack_Connection::is_connected() ) {
			return null;
		}

		$cached = get_site_transient( self::CACHE );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$info = AffilStack_Connection::request( 'GET', '/api/wordpress/plugin-info' );

		if ( is_wp_error( $info ) || empty( $info['version'] ) ) {
			// Try again in an hour rather than on every page load.
			set_site_transient( self::CACHE, array(), HOUR_IN_SECONDS );

			return null;
		}

		set_site_transient( self::CACHE, $info, 6 * HOUR_IN_SECONDS );

		return $info;
	}

	/**
	 * @param object $transient
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info   = self::info();
		$plugin = plugin_basename( AFFILSTACK_CONNECTOR_FILE );

		if ( ! $info || empty( $info['version'] ) || empty( $info['download_url'] ) || version_compare( $info['version'], AFFILSTACK_CONNECTOR_VERSION, '<=' ) ) {
			return $transient;
		}

		$transient->response[ $plugin ] = (object) array(
			'slug'         => self::SLUG,
			'plugin'       => $plugin,
			'new_version'  => $info['version'],
			'package'      => $info['download_url'],
			'url'          => isset( $info['homepage'] ) ? $info['homepage'] : '',
			'tested'       => isset( $info['tested'] ) ? $info['tested'] : '',
			'requires'     => isset( $info['requires'] ) ? $info['requires'] : '',
			'requires_php' => isset( $info['requires_php'] ) ? $info['requires_php'] : '',
		);

		return $transient;
	}

	/**
	 * The "View details" popup on the Plugins screen.
	 */
	public static function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$info      = self::info();
		$changelog = '';

		if ( $info && ! empty( $info['changelog'] ) && is_array( $info['changelog'] ) ) {
			foreach ( $info['changelog'] as $version => $note ) {
				$changelog .= '<h4>' . esc_html( $version ) . '</h4><p>' . esc_html( $note ) . '</p>';
			}
		}

		return (object) array(
			'name'          => 'AffilStack Connector',
			'slug'          => self::SLUG,
			'version'       => $info && ! empty( $info['version'] ) ? $info['version'] : AFFILSTACK_CONNECTOR_VERSION,
			'author'        => '<a href="' . esc_url( AffilStack_Connection::base_url() ) . '">AffilStack</a>',
			'homepage'      => $info && ! empty( $info['homepage'] ) ? $info['homepage'] : AffilStack_Connection::base_url(),
			'requires'      => $info && ! empty( $info['requires'] ) ? $info['requires'] : '',
			'tested'        => $info && ! empty( $info['tested'] ) ? $info['tested'] : '',
			'requires_php'  => $info && ! empty( $info['requires_php'] ) ? $info['requires_php'] : '',
			'download_link' => $info && ! empty( $info['download_url'] ) ? $info['download_url'] : '',
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Receive your AffilStack blog articles as WordPress drafts, with images, SEO fields, categories, tags, FAQ schema and your tracked affiliate links.', 'affilstack-connector' ) . '</p>',
				'changelog'   => $changelog ? $changelog : '<p>' . esc_html__( 'See your AffilStack dashboard.', 'affilstack-connector' ) . '</p>',
			),
		);
	}
}
