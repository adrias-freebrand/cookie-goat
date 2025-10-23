<?php
/**
 * Uninstall cleanup for Cookie GOAT plugin.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$option_names = array(
    'cookiegoat_settings',
    'cookiegoat_scan_results'
);

foreach ( $option_names as $option ) {
    delete_option( $option );
}

$table = $wpdb->prefix . 'cookiegoat_consent_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
