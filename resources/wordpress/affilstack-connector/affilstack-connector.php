<?php
/**
 * Plugin Name:       AffilStack Connector
 * Plugin URI:        __AFFILSTACK_URL__/wordpress
 * Description:       Receive your AffilStack blog articles as WordPress drafts, with images, SEO fields, categories, tags, FAQ schema and your tracked affiliate links. Review and publish when you're ready.
 * Version:           __AFFILSTACK_VERSION__
 * Requires at least: __AFFILSTACK_REQUIRES_WP__
 * Requires PHP:      __AFFILSTACK_REQUIRES_PHP__
 * Author:            AffilStack
 * Author URI:        __AFFILSTACK_URL__
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       affilstack-connector
 *
 * @package AffilStack_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AFFILSTACK_CONNECTOR_VERSION', '__AFFILSTACK_VERSION__' );
define( 'AFFILSTACK_CONNECTOR_FILE', __FILE__ );
define( 'AFFILSTACK_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );

// The AffilStack address this copy of the plugin talks to. Written in when
// the plugin is downloaded; can be overridden in wp-config.php.
if ( ! defined( 'AFFILSTACK_CONNECTOR_BASE_URL' ) ) {
	define( 'AFFILSTACK_CONNECTOR_BASE_URL', '__AFFILSTACK_URL__' );
}

require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-signature.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-connection.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-importer.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-rest.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-admin.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-schema.php';
require_once AFFILSTACK_CONNECTOR_DIR . 'includes/class-affilstack-updater.php';

add_action( 'rest_api_init', array( 'AffilStack_Rest', 'register_routes' ) );
add_action( 'wp_head', array( 'AffilStack_Schema', 'print_faq_schema' ), 20 );

if ( is_admin() ) {
	AffilStack_Admin::init();
}

AffilStack_Updater::init();

register_deactivation_hook( __FILE__, array( 'AffilStack_Updater', 'clear_cache' ) );
