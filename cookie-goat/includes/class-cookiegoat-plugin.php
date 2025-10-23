<?php
/**
 * Core loader for Cookie GOAT plugin.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-settings.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-scanner.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-logger.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-consent.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-policy.php';
require_once COOKIEGOAT_PLUGIN_DIR . 'includes/class-cookiegoat-gcm.php';

/**
 * Main plugin orchestrator.
 */
class CookieGoat_Plugin {
    /**
     * Settings handler.
     *
     * @var CookieGoat_Settings
     */
    private CookieGoat_Settings $settings;

    /**
     * Scanner handler.
     *
     * @var CookieGoat_Scanner
     */
    private CookieGoat_Scanner $scanner;

    /**
     * Logger handler.
     *
     * @var CookieGoat_Logger
     */
    private CookieGoat_Logger $logger;

    /**
     * Consent handler.
     *
     * @var CookieGoat_Consent
     */
    private CookieGoat_Consent $consent;

    /**
     * Policy helper.
     *
     * @var CookieGoat_Policy
     */
    private CookieGoat_Policy $policy;

    /**
     * GCM helper.
     *
     * @var CookieGoat_GCM
     */
    private CookieGoat_GCM $gcm;

    /**
     * Boot plugin services.
     */
    public function __construct() {
        $this->settings = new CookieGoat_Settings();
        $this->scanner  = new CookieGoat_Scanner( $this->settings );
        $this->logger   = new CookieGoat_Logger( $this->settings );
        $this->consent  = new CookieGoat_Consent( $this->settings, $this->logger );
        $this->policy   = new CookieGoat_Policy( $this->settings );
        $this->gcm      = new CookieGoat_GCM( $this->settings, $this->consent, $this->logger );
    }

    /**
     * Register hooks.
     */
    public function run() : void {
        $this->settings->register();
        $this->scanner->register();
        $this->logger->register();
        $this->consent->register();
        $this->policy->register();
        $this->gcm->register();

        add_action( 'cookiegoat_daily_event', array( $this, 'run_daily_tasks' ) );
    }

    /**
     * Execute recurring tasks.
     */
    public function run_daily_tasks() : void {
        $this->scanner->maybe_schedule_scan();
        $this->consent->maybe_expire_consent();
    }
}
