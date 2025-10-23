<?php
/**
 * Plugin Name: Cookie GOAT 🐐 – CMP + GCM v2 Advanced
 * Plugin URI: https://example.com/cookie-goat
 * Description: Gestión de cookies compatible con RGPD y Google Consent Mode v2 en modo avanzado.
 * Version: 1.0.0
 * Author: Cookie GOAT Team
 * Author URI: https://example.com/
 * Text Domain: cookie-goat
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'COOKIEGOAT_VERSION' ) ) {
define( 'COOKIEGOAT_VERSION', '1.0.0' );
}

if ( ! defined( 'COOKIEGOAT_PLUGIN_FILE' ) ) {
define( 'COOKIEGOAT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'COOKIEGOAT_PLUGIN_DIR' ) ) {
define( 'COOKIEGOAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'COOKIEGOAT_PLUGIN_URL' ) ) {
define( 'COOKIEGOAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Load plugin textdomain.
add_action( 'plugins_loaded', 'cookiegoat_load_textdomain' );
/**
 * Load translations.
 */
function cookiegoat_load_textdomain() : void {
load_plugin_textdomain( 'cookie-goat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-activator.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-deactivator.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-plugin.php';

register_activation_hook( __FILE__, array( 'CookieGoat_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CookieGoat_Deactivator', 'deactivate' ) );

/**
 * Initialize plugin instance after WordPress has loaded.
 */
function cookiegoat_run_plugin() : void {
	$plugin = new CookieGoat_Plugin();
	$plugin->run();
}

add_action( 'plugins_loaded', 'cookiegoat_run_plugin', 20 );
