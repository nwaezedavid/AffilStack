<?php
/**
 * The REST endpoints AffilStack calls (wp-json/affilstack/v1/…). Every
 * request must carry a valid signature made with this site's secret.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Rest {

	const NAMESPACE_V1 = 'affilstack/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/posts',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_post' ),
				'permission_callback' => array( __CLASS__, 'verify' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'verify' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'disconnect' ),
				'permission_callback' => array( __CLASS__, 'verify' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function verify( WP_REST_Request $request ) {
		$connection = AffilStack_Connection::get();

		if ( empty( $connection['site_id'] ) || empty( $connection['secret'] ) ) {
			return new WP_Error( 'affilstack_not_connected', __( 'This site is not connected to AffilStack.', 'affilstack-connector' ), array( 'status' => 401 ) );
		}

		$site_id = (string) $request->get_header( 'x_affilstack_site' );

		$valid = (string) $connection['site_id'] === $site_id && AffilStack_Signature::verify(
			$connection['secret'],
			(string) $request->get_header( 'x_affilstack_timestamp' ),
			(string) $request->get_header( 'x_affilstack_signature' ),
			$request->get_method(),
			$request->get_route(),
			(string) $request->get_body()
		);

		if ( ! $valid ) {
			return new WP_Error( 'affilstack_not_connected', __( 'The request signature is not valid.', 'affilstack-connector' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_post( WP_REST_Request $request ) {
		$article = json_decode( (string) $request->get_body(), true );

		if ( ! is_array( $article ) ) {
			return new WP_Error( 'affilstack_invalid', __( 'The article could not be read.', 'affilstack-connector' ), array( 'status' => 400 ) );
		}

		// Imports run without a signed-in user, so give the request the
		// connecting user's identity: their role decides what HTML survives.
		$connection = AffilStack_Connection::get();
		if ( ! empty( $connection['connected_by'] ) && get_userdata( (int) $connection['connected_by'] ) ) {
			wp_set_current_user( (int) $connection['connected_by'] );
		}

		$result = AffilStack_Importer::import( $article );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, $result['updated'] ? 200 : 201 );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function status() {
		return new WP_REST_Response( AffilStack_Connection::site_info(), 200 );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function disconnect() {
		AffilStack_Connection::forget();

		return new WP_REST_Response( array( 'disconnected' => true ), 200 );
	}
}
