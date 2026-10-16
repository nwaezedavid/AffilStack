<?php
/**
 * Request signing, mirroring AffilStack's ConnectorSignature exactly:
 * HMAC-SHA256 over "timestamp \n METHOD \n path \n sha256(body)" with the
 * secret this site received when it was connected.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffilStack_Signature {

	const WINDOW = 300;

	/**
	 * @param string $secret    Shared secret.
	 * @param int    $timestamp Unix time.
	 * @param string $method    HTTP method.
	 * @param string $path      Request path or REST route.
	 * @param string $body      Raw body.
	 * @return string
	 */
	public static function sign( $secret, $timestamp, $method, $path, $body ) {
		return hash_hmac( 'sha256', $timestamp . "\n" . strtoupper( $method ) . "\n" . self::normalize_path( $path ) . "\n" . hash( 'sha256', $body ), $secret );
	}

	/**
	 * @return array<string, string>
	 */
	public static function headers( $site_id, $secret, $method, $path, $body ) {
		$timestamp = time();

		return array(
			'X-AffilStack-Site'      => (string) $site_id,
			'X-AffilStack-Timestamp' => (string) $timestamp,
			'X-AffilStack-Signature' => self::sign( $secret, $timestamp, $method, $path, $body ),
		);
	}

	/**
	 * @return bool
	 */
	public static function verify( $secret, $timestamp, $signature, $method, $path, $body ) {
		if ( ! is_string( $timestamp ) || ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::WINDOW ) {
			return false;
		}

		if ( ! is_string( $signature ) || '' === $signature || '' === (string) $secret ) {
			return false;
		}

		return hash_equals( self::sign( $secret, (int) $timestamp, $method, $path, $body ), strtolower( $signature ) );
	}

	/**
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$path   = is_string( $parsed ) ? $parsed : (string) $path;

		return '/' . trim( $path, '/' );
	}
}
