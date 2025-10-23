<?php
/**
 * Automatic scanner for cookies and storage.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles scanning the site to detect cookies and storage usage.
 */
class CookieGoat_Scanner {
    /**
     * Settings handler.
     *
     * @var CookieGoat_Settings
     */
    private CookieGoat_Settings $settings;

    /**
     * Option name for scan results.
     */
    public const RESULTS_OPTION = 'cookiegoat_scan_results';

    /**
     * Constructor.
     *
     * @param CookieGoat_Settings $settings Settings handler.
     */
    public function __construct( CookieGoat_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Register hooks.
     */
    public function register() : void {
        add_action( 'admin_post_cookiegoat_manual_scan', array( $this, 'handle_manual_scan' ) );
    }

    /**
     * Maybe run scheduled scan.
     */
    public function maybe_schedule_scan() : void {
        $settings = $this->settings->get_settings();
        $last_run = isset( $settings['autoscan_last_run'] ) ? (int) $settings['autoscan_last_run'] : 0;
        $frequency = isset( $settings['autoscan_frequency'] ) ? (int) $settings['autoscan_frequency'] : DAY_IN_SECONDS * 7;

        if ( time() - $last_run >= $frequency ) {
            $this->run_scan();
        }
    }

    /**
     * Handle manual scan requests.
     */
    public function handle_manual_scan() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'cookie-goat' ) );
        }

        check_admin_referer( 'cookiegoat_manual_scan', 'cookiegoat_nonce' );
        $this->run_scan();

        wp_safe_redirect( add_query_arg( 'cookiegoat_scan', 'success', admin_url( 'options-general.php?page=cookiegoat-settings' ) ) );
        exit;
    }

    /**
     * Perform the scan and save results.
     */
    public function run_scan() : void {
        $response = wp_remote_get( home_url(), array( 'timeout' => 15 ) );

        $cookies = array();
        $storage = array();

        if ( ! is_wp_error( $response ) ) {
            $cookies = $this->extract_cookies( $response );
            $storage = $this->extract_storage( wp_remote_retrieve_body( $response ) );
        }

        $results = array(
            'scanned_at' => time(),
            'cookies'    => $cookies,
            'storage'    => $storage,
        );

        update_option( self::RESULTS_OPTION, $results );

        $settings                      = $this->settings->get_settings();
        $settings['autoscan_last_run'] = time();
        update_option( CookieGoat_Settings::OPTION_NAME, $settings );
    }

    /**
     * Get stored scan results.
     *
     * @return array<string, mixed>
     */
    public function get_scan_results() : array {
        $defaults = array(
            'scanned_at' => 0,
            'cookies'    => array(),
            'storage'    => array(),
        );

        $results = get_option( self::RESULTS_OPTION, $defaults );
        if ( ! is_array( $results ) ) {
            $results = $defaults;
        }

        return wp_parse_args( $results, $defaults );
    }

    /**
     * Extract cookies from response.
     *
     * @param array<string, mixed> $response Response array.
     * @return array<int, array<string, string>>
     */
    private function extract_cookies( array $response ) : array {
        $headers = wp_remote_retrieve_headers( $response );
        $cookies = array();

        if ( isset( $headers['set-cookie'] ) ) {
            $set_cookie = $headers['set-cookie'];
            if ( is_string( $set_cookie ) ) {
                $set_cookie = array( $set_cookie );
            }

            foreach ( $set_cookie as $header ) {
                $parsed = $this->parse_cookie_header( $header );
                if ( ! empty( $parsed ) ) {
                    $cookies[] = $parsed;
                }
            }
        }

        $existing_cookies = wp_unslash( $_COOKIE );
        if ( is_array( $existing_cookies ) ) {
            foreach ( $existing_cookies as $name => $value ) {
                $cookies[] = $this->format_cookie_entry( sanitize_text_field( (string) $name ), (string) $value, '' );
            }
        }

        return $this->dedupe_cookies( $cookies );
    }

    /**
     * Extract storage keys from markup.
     *
     * @param string $body HTML body.
     * @return array<int, array<string, string>>
     */
    private function extract_storage( string $body ) : array {
        $storage = array();

        if ( preg_match_all( '/localStorage\.setItem\(["\']([^"\']+)/i', $body, $matches ) ) {
            foreach ( $matches[1] as $key ) {
                $storage[] = array(
                    'type'     => 'localStorage',
                    'key'      => sanitize_text_field( $key ),
                    'category' => $this->classify_cookie( $key ),
                );
            }
        }

        if ( preg_match_all( '/sessionStorage\.setItem\(["\']([^"\']+)/i', $body, $matches ) ) {
            foreach ( $matches[1] as $key ) {
                $storage[] = array(
                    'type'     => 'sessionStorage',
                    'key'      => sanitize_text_field( $key ),
                    'category' => $this->classify_cookie( $key ),
                );
            }
        }

        return $storage;
    }

    /**
     * Parse a Set-Cookie header into structured data.
     *
     * @param string $header Header string.
     * @return array<string, string>
     */
    private function parse_cookie_header( string $header ) : array {
        $parts  = explode( ';', $header );
        $cookie = array_shift( $parts );
        if ( empty( $cookie ) || false === strpos( $cookie, '=' ) ) {
            return array();
        }

        list( $name, $value ) = array_map( 'trim', explode( '=', $cookie, 2 ) );
        $name  = sanitize_text_field( $name );
        $value = sanitize_text_field( $value );

        $duration = __( 'Sesión', 'cookie-goat' );
        $provider = parse_url( home_url(), PHP_URL_HOST );

        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( stripos( $part, 'expires=' ) === 0 ) {
                $expires  = trim( substr( $part, 8 ) );
                $duration = $this->calculate_duration( $expires );
            }
            if ( stripos( $part, 'domain=' ) === 0 ) {
                $provider = trim( substr( $part, 7 ) );
            }
        }

        return $this->format_cookie_entry( $name, $value, $provider, $duration );
    }

    /**
     * Build cookie entry.
     *
     * @param string $name Cookie name.
     * @param string $value Cookie value.
     * @param string $provider Cookie provider.
     * @param string $duration Duration string.
     * @return array<string, string>
     */
    private function format_cookie_entry( string $name, string $value, string $provider, string $duration = '' ) : array {
        $category = $this->classify_cookie( $name );
        $purposes = array(
            'necessary'   => __( 'Requerida para ofrecer servicios básicos del sitio.', 'cookie-goat' ),
            'preferences' => __( 'Recuerda preferencias de experiencia y personalización.', 'cookie-goat' ),
            'analytics'   => __( 'Permite medir el uso y el rendimiento del sitio.', 'cookie-goat' ),
            'marketing'   => __( 'Se usa para personalizar publicidad y medir conversiones.', 'cookie-goat' ),
        );

        $purpose = isset( $purposes[ $category ] ) ? $purposes[ $category ] : $purposes['necessary'];

        return array(
            'name'      => $name,
            'provider'  => $provider,
            'duration'  => $duration ? $duration : __( 'Sesión', 'cookie-goat' ),
            'category'  => $category,
            'purpose'   => $purpose,
            'hash'      => md5( $name . '|' . $provider . '|' . $category ),
            'value_len' => (string) strlen( $value ),
        );
    }

    /**
     * Calculate human readable duration.
     *
     * @param string $expires Expiration string.
     * @return string
     */
    private function calculate_duration( string $expires ) : string {
        $timestamp = strtotime( $expires );
        if ( ! $timestamp ) {
            return __( 'Sesión', 'cookie-goat' );
        }

        $seconds = $timestamp - time();
        if ( $seconds <= 0 ) {
            return __( 'Sesión', 'cookie-goat' );
        }

        $days = floor( $seconds / DAY_IN_SECONDS );
        if ( $days < 1 ) {
            $hours = max( 1, (int) floor( $seconds / HOUR_IN_SECONDS ) );
            return sprintf( _n( '%d hora', '%d horas', $hours, 'cookie-goat' ), $hours );
        }

        return sprintf( _n( '%d día', '%d días', $days, 'cookie-goat' ), $days );
    }

    /**
     * Classify cookie into categories.
     *
     * @param string $name Cookie name.
     * @return string
     */
    private function classify_cookie( string $name ) : string {
        $name = strtolower( $name );

        $map = array(
            'necessary'   => array( 'wordpress', 'wp-', 'woocommerce', 'phpsessid', 'cookie_notice_accepted' ),
            'preferences' => array( 'lang', 'locale', 'pref', 'pll_language' ),
            'analytics'   => array( 'ga', '_gid', '_gat', '_gcl', '_fbp', 'matomo', 'stats', 'ym_uid' ),
            'marketing'   => array( 'ads', 'gads', 'fr', 'tr', 'tt_viewer', 'ninja_pixel' ),
        );

        foreach ( $map as $category => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( str_contains( $name, strtolower( $keyword ) ) ) {
                    return $category;
                }
            }
        }

        return 'necessary';
    }

    /**
     * Remove duplicated entries by hash.
     *
     * @param array<int, array<string, string>> $cookies Cookies list.
     * @return array<int, array<string, string>>
     */
    private function dedupe_cookies( array $cookies ) : array {
        $unique = array();
        foreach ( $cookies as $cookie ) {
            if ( empty( $cookie['hash'] ) ) {
                continue;
            }
            $unique[ $cookie['hash'] ] = $cookie;
        }

        return array_values( $unique );
    }
}
