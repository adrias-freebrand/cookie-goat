<?php
/**
 * Activator for Cookie GOAT plugin.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin activation tasks.
 */
class CookieGoat_Activator {
    /**
     * Run on activation.
     */
    public static function activate() : void {
        self::create_tables();
        self::register_options();
        self::schedule_events();
    }

    /**
     * Create database tables.
     */
    private static function create_tables() : void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'cookiegoat_consent_log';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            consent_time datetime NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            hashed_ip varchar(128) DEFAULT NULL,
            decision longtext NOT NULL,
            policy_version varchar(32) NOT NULL,
            PRIMARY KEY  (id),
            KEY consent_time (consent_time)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Register default options.
     */
    private static function register_options() : void {
        $defaults = array(
            'banner_title'            => __( 'Gestiona tu privacidad', 'cookie-goat' ),
            'banner_description'      => __( 'Utilizamos cookies para mejorar la experiencia, analizar el tráfico y personalizar contenidos. Puedes aceptar, rechazar o configurar tus preferencias.', 'cookie-goat' ),
            'policy_link'             => home_url( '/politica-de-cookies/' ),
            'policy_version'          => '1.0',
            'consent_expiration_days' => 730,
            'floating_button_label'   => __( 'Preferencias de cookies', 'cookie-goat' ),
            'autoscan_last_run'       => 0,
            'autoscan_frequency'      => DAY_IN_SECONDS * 7,
            'gtm_container_id'        => '',
        );

        if ( false === get_option( 'cookiegoat_settings', false ) ) {
            add_option( 'cookiegoat_settings', $defaults );
        }

        if ( false === get_option( 'cookiegoat_scan_results', false ) ) {
            add_option( 'cookiegoat_scan_results', array() );
        }
    }

    /**
     * Schedule cron events.
     */
    private static function schedule_events() : void {
        if ( ! wp_next_scheduled( 'cookiegoat_daily_event' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cookiegoat_daily_event' );
        }
    }
}
