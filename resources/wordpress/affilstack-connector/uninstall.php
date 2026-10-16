<?php
/**
 * Removes the plugin's settings when it is deleted. Imported posts, their
 * images and SEO fields stay: they belong to the site now.
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'affilstack_connector_connection' );
delete_site_transient( 'affilstack_connector_update_info' );
