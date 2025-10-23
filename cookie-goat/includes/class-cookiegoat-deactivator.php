<?php
/**
 * Deactivator for Cookie GOAT plugin.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation tasks.
 */
class CookieGoat_Deactivator {
    /**
     * Run on deactivation.
     */
    public static function deactivate() : void {
        $timestamp = wp_next_scheduled( 'cookiegoat_daily_event' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cookiegoat_daily_event' );
        }
    }
}
