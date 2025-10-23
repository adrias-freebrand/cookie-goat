<?php
/**
 * Google Consent Mode v2 integration.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles generation of the consent stub for GCM v2.
 */
class CookieGoat_GCM {
    /**
     * Settings handler.
     *
     * @var CookieGoat_Settings
     */
    private CookieGoat_Settings $settings;

    /**
     * Consent handler.
     *
     * @var CookieGoat_Consent
     */
    private CookieGoat_Consent $consent;

    /**
     * Logger instance.
     *
     * @var CookieGoat_Logger
     */
    private CookieGoat_Logger $logger;

    /**
     * Constructor.
     *
     * @param CookieGoat_Settings $settings Settings.
     * @param CookieGoat_Consent  $consent  Consent.
     * @param CookieGoat_Logger   $logger   Logger.
     */
    public function __construct( CookieGoat_Settings $settings, CookieGoat_Consent $consent, CookieGoat_Logger $logger ) {
        $this->settings = $settings;
        $this->consent  = $consent;
        $this->logger   = $logger;
    }

    /**
     * Register hooks.
     */
    public function register() : void {
        add_action( 'wp_head', array( $this, 'print_default_consent' ), 0 );
    }

    /**
     * Print the GCM default consent stub.
     */
    public function print_default_consent() : void {
        if ( is_admin() ) {
            return;
        }

        $consent   = $this->consent->get_current_consent();
        $settings  = $this->settings->get_settings();
        $granted   = array(
            'ad_storage'         => 'denied',
            'analytics_storage'  => 'denied',
            'ad_user_data'       => 'denied',
            'ad_personalization' => 'denied',
        );

        if ( ! empty( $consent['categories'] ) && is_array( $consent['categories'] ) ) {
            if ( ! empty( $consent['categories']['analytics'] ) ) {
                $granted['analytics_storage'] = 'granted';
            }
            if ( ! empty( $consent['categories']['marketing'] ) ) {
                $granted['ad_storage']         = 'granted';
                $granted['ad_user_data']       = 'granted';
                $granted['ad_personalization'] = 'granted';
            }
        }

        $has_consent = ! empty( $consent['status'] ) && 'given' === $consent['status'];
        ?>
        <script data-cookiegoat="gcm">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'analytics_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied'
            });
            gtag('set', 'ads_data_redaction', true);
            gtag('set', 'url_passthrough', true);
            <?php if ( $has_consent ) : ?>
            gtag('consent', 'update', <?php echo wp_json_encode( $granted ); ?>);
            <?php endif; ?>
            window.cookiegoatGCM = {
                defaults: {
                    ad_storage: 'denied',
                    analytics_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied'
                },
                lastState: <?php echo wp_json_encode( $granted ); ?>
            };
        </script>
        <?php if ( ! empty( $settings['gtm_container_id'] ) ) : ?>
            <script data-cookiegoat="gtm-hint">
                window.cookiegoatGTM = '<?php echo esc_js( $settings['gtm_container_id'] ); ?>';
            </script>
        <?php endif;
    }
}
