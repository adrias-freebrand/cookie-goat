<?php
/**
 * Front-end consent management and blocking logic.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles consent UI, cookies and blocking logic.
 */
class CookieGoat_Consent {
    /**
     * Consent cookie name.
     */
    public const COOKIE_NAME = 'cookiegoat_consent';

    /**
     * Settings handler.
     *
     * @var CookieGoat_Settings
     */
    private CookieGoat_Settings $settings;

    /**
     * Logger instance.
     *
     * @var CookieGoat_Logger
     */
    private CookieGoat_Logger $logger;

    /**
     * Registered script categories.
     *
     * @var array<string, string>
     */
    private array $script_categories = array();

    /**
     * Constructor.
     *
     * @param CookieGoat_Settings $settings Settings handler.
     * @param CookieGoat_Logger   $logger   Logger handler.
     */
    public function __construct( CookieGoat_Settings $settings, CookieGoat_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    /**
     * Register hooks.
     */
    public function register() : void {
        add_action( 'init', array( $this, 'maybe_expire_consent' ) );
        add_action( 'init', array( $this, 'bootstrap_script_registry' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_ui' ) );
        add_action( 'wp_body_open', array( $this, 'render_ui' ), 1 );
        add_action( 'wp_ajax_cookiegoat_update_consent', array( $this, 'handle_ajax_update' ) );
        add_action( 'wp_ajax_nopriv_cookiegoat_update_consent', array( $this, 'handle_ajax_update' ) );
        add_filter( 'script_loader_tag', array( $this, 'filter_script_loading' ), 10, 3 );
        add_shortcode( 'cookiegoat_preferences', array( $this, 'render_preferences_shortcode' ) );
    }

    /**
     * Register default script categories via filter.
     */
    public function bootstrap_script_registry() : void {
        $default_registry = array(
            'google-analytics' => 'analytics',
            'ga-google-analytics' => 'analytics',
            'google-ads' => 'marketing',
            'facebook-pixel' => 'marketing',
        );

        $this->script_categories = apply_filters( 'cookiegoat_script_categories', $default_registry );
    }

    /**
     * Enqueue front-end assets.
     */
    public function enqueue_assets() : void {
        if ( is_admin() ) {
            return;
        }

        wp_register_style(
            'cookiegoat-frontend',
            COOKIEGOAT_PLUGIN_URL . 'assets/css/banner.min.css',
            array(),
            COOKIEGOAT_VERSION
        );

        wp_register_script(
            'cookiegoat-frontend',
            COOKIEGOAT_PLUGIN_URL . 'assets/js/banner.min.js',
            array(),
            COOKIEGOAT_VERSION,
            true
        );

        $settings   = $this->settings->get_settings();
        $consent    = $this->get_current_consent();
        $scan_data  = get_option( CookieGoat_Scanner::RESULTS_OPTION, array() );
        $local_data = array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'cookiegoat_update_consent' ),
            'settings'       => array(
                'bannerTitle'       => $settings['banner_title'],
                'bannerDescription' => wp_kses_post( $settings['banner_description'] ),
                'policyLink'        => esc_url( $settings['policy_link'] ),
                'floatingLabel'     => $settings['floating_button_label'],
                'categoryTexts'     => $settings['category_texts'],
                'consentExpiration' => (int) $settings['consent_expiration_days'],
                'policyVersion'     => $settings['policy_version'],
            ),
            'consent'        => $consent,
            'scan'           => $scan_data,
            'categories'     => $this->get_categories_schema(),
            'gtmContainer'   => $settings['gtm_container_id'],
            'cookiePath'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
            'cookieDomain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
        );

        wp_localize_script( 'cookiegoat-frontend', 'cookiegoatData', $local_data );
        wp_enqueue_style( 'cookiegoat-frontend' );
        wp_enqueue_script( 'cookiegoat-frontend' );
    }

    /**
     * Render front-end UI markup.
     */
    public function render_ui() : void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;

        $settings    = $this->settings->get_settings();
        $consent     = $this->get_current_consent();
        $categories  = $this->get_categories_schema();
        $should_show = empty( $consent['status'] ) || 'given' !== $consent['status'];
        ?>
        <div class="cookiegoat-container" role="region" aria-live="polite" data-visible="<?php echo esc_attr( $should_show ? '1' : '0' ); ?>">
            <div class="cookiegoat-banner" role="dialog" aria-modal="true" aria-labelledby="cookiegoat-banner-title" aria-describedby="cookiegoat-banner-desc">
                <h2 id="cookiegoat-banner-title"><?php echo esc_html( $settings['banner_title'] ); ?></h2>
                <p id="cookiegoat-banner-desc"><?php echo wp_kses_post( $settings['banner_description'] ); ?></p>
                <p class="cookiegoat-responsable">
                    <?php echo esc_html__( 'Responsable: ', 'cookie-goat' ) . esc_html( get_bloginfo( 'name' ) ); ?>
                </p>
                <div class="cookiegoat-actions" role="group" aria-label="<?php esc_attr_e( 'Opciones de consentimiento', 'cookie-goat' ); ?>">
                    <button class="cookiegoat-btn cookiegoat-accept" data-action="accept">
                        <?php esc_html_e( 'Aceptar todo', 'cookie-goat' ); ?>
                    </button>
                    <button class="cookiegoat-btn cookiegoat-reject" data-action="reject">
                        <?php esc_html_e( 'Rechazar todo', 'cookie-goat' ); ?>
                    </button>
                    <button class="cookiegoat-btn cookiegoat-configure" data-action="configure">
                        <?php esc_html_e( 'Configurar', 'cookie-goat' ); ?>
                    </button>
                </div>
                <a class="cookiegoat-policy" href="<?php echo esc_url( $settings['policy_link'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Política de cookies', 'cookie-goat' ); ?>
                </a>
            </div>
            <button class="cookiegoat-floating" type="button" aria-haspopup="dialog" aria-controls="cookiegoat-modal">
                <?php echo esc_html( $settings['floating_button_label'] ); ?>
            </button>
        </div>
        <div class="cookiegoat-modal" id="cookiegoat-modal" role="dialog" aria-modal="true" aria-labelledby="cookiegoat-modal-title" aria-describedby="cookiegoat-modal-desc" hidden>
            <div class="cookiegoat-modal__inner">
                <button type="button" class="cookiegoat-close" aria-label="<?php esc_attr_e( 'Cerrar', 'cookie-goat' ); ?>">&times;</button>
                <h2 id="cookiegoat-modal-title"><?php esc_html_e( 'Configura tus preferencias', 'cookie-goat' ); ?></h2>
                <p id="cookiegoat-modal-desc"><?php esc_html_e( 'Selecciona las categorías que deseas habilitar. Las cookies esenciales no pueden desactivarse.', 'cookie-goat' ); ?></p>
                <ul class="cookiegoat-category-list">
                    <?php foreach ( $categories as $category => $data ) :
                        $checked = isset( $consent['categories'][ $category ] ) ? (bool) $consent['categories'][ $category ] : ( 'necessary' === $category );
                        ?>
                        <li class="cookiegoat-category" data-category="<?php echo esc_attr( $category ); ?>">
                            <div class="cookiegoat-category__header">
                                <span class="cookiegoat-category__title"><?php echo esc_html( $data['label'] ); ?></span>
                                <label class="cookiegoat-switch">
                                    <input type="checkbox" <?php checked( $checked ); ?> <?php disabled( 'necessary' === $category ); ?> />
                                    <span class="cookiegoat-slider" aria-hidden="true"></span>
                                </label>
                            </div>
                            <p class="cookiegoat-category__desc"><?php echo esc_html( $data['description'] ); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="cookiegoat-modal__actions">
                    <button class="cookiegoat-btn cookiegoat-save" type="button"><?php esc_html_e( 'Guardar preferencias', 'cookie-goat' ); ?></button>
                    <button class="cookiegoat-btn cookiegoat-cancel" type="button"><?php esc_html_e( 'Cancelar', 'cookie-goat' ); ?></button>
                </div>
                <div class="cookiegoat-legal-version">
                    <?php echo esc_html( sprintf( /* translators: %s: policy version */ __( 'Versión legal vigente: %s', 'cookie-goat' ), $settings['policy_version'] ) ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Provide categories schema for UI and storage.
     *
     * @return array<string, array<string, string>>
     */
    private function get_categories_schema() : array {
        $settings = $this->settings->get_settings();
        return array(
            'necessary'   => array(
                'label'       => __( 'Esenciales', 'cookie-goat' ),
                'description' => $settings['category_texts']['necessary'],
            ),
            'preferences' => array(
                'label'       => __( 'Preferencias', 'cookie-goat' ),
                'description' => $settings['category_texts']['preferences'],
            ),
            'analytics'   => array(
                'label'       => __( 'Analíticas', 'cookie-goat' ),
                'description' => $settings['category_texts']['analytics'],
            ),
            'marketing'   => array(
                'label'       => __( 'Marketing', 'cookie-goat' ),
                'description' => $settings['category_texts']['marketing'],
            ),
        );
    }

    /**
     * Handle AJAX consent update.
     */
    public function handle_ajax_update() : void {
        if ( ! check_ajax_referer( 'cookiegoat_update_consent', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce inválido.', 'cookie-goat' ) ), 403 );
        }

        $raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
        if ( is_string( $raw_data ) ) {
            $raw_data = json_decode( $raw_data, true );
        }

        if ( ! is_array( $raw_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Datos incorrectos.', 'cookie-goat' ) ), 400 );
        }

        $categories = $this->get_categories_schema();
        $decisions  = array();

        foreach ( $categories as $key => $data ) {
            if ( 'necessary' === $key ) {
                $decisions[ $key ] = true;
                continue;
            }
            $decisions[ $key ] = isset( $raw_data['categories'][ $key ] ) ? (bool) $raw_data['categories'][ $key ] : false;
        }

        $status = 'given';

        $settings = $this->settings->get_settings();
        $overall = 'denied';
        foreach ( $decisions as $key => $allowed ) {
            if ( 'necessary' === $key ) {
                continue;
            }
            if ( true === $allowed ) {
                $overall = 'partial';
                if ( ! empty( $decisions['marketing'] ) && ! empty( $decisions['analytics'] ) && ! empty( $decisions['preferences'] ) ) {
                    $overall = 'granted';
                }
            }
        }

        $consent  = array(
            'status'     => $status,
            'timestamp'  => time(),
            'version'    => $settings['policy_version'],
            'categories' => $decisions,
            'overall'    => $overall,
        );

        $this->set_consent_cookie( $consent, $settings['consent_expiration_days'] );
        $this->logger->log_consent( $consent );

        wp_send_json_success( array( 'consent' => $consent ) );
    }

    /**
     * Determine whether a given script should run.
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     * @param string $src    Script source.
     * @return string
     */
    public function filter_script_loading( string $tag, string $handle, string $src ) : string {
        if ( is_admin() ) {
            return $tag;
        }

        $category = $this->script_categories[ $handle ] ?? '';
        if ( empty( $category ) || 'necessary' === $category ) {
            return $tag;
        }

        $consent = $this->get_current_consent();
        if ( empty( $consent['categories'][ $category ] ) ) {
            return sprintf( '<!-- cookiegoat: blocked %s (%s) -->', esc_html( $handle ), esc_html( $category ) );
        }

        return $tag;
    }

    /**
     * Expose shortcode for revocation.
     *
     * @return string
     */
    public function render_preferences_shortcode() : string {
        $settings = $this->settings->get_settings();
        return '<button type="button" class="cookiegoat-shortcode-button" data-cookiegoat-open="1">' . esc_html( $settings['floating_button_label'] ) . '</button>';
    }

    /**
     * Retrieve current consent data.
     *
     * @return array<string, mixed>
     */
    public function get_current_consent() : array {
        $raw = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) : '';
        if ( empty( $raw ) ) {
            return array(
                'status'     => 'denied',
                'timestamp'  => 0,
                'version'    => '',
                'categories' => array(),
            );
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'status'     => 'denied',
                'timestamp'  => 0,
                'version'    => '',
                'categories' => array(),
            );
        }

        return $decoded;
    }

    /**
     * Set consent cookie securely.
     *
     * @param array<string, mixed> $consent Consent data.
     * @param int                  $days    Expiration days.
     */
    private function set_consent_cookie( array $consent, int $days ) : void {
        if ( headers_sent() ) {
            return;
        }
        $expiration = time() + ( $days * DAY_IN_SECONDS );
        $encoded    = wp_json_encode( $consent );
        $path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
        $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

        setcookie( self::COOKIE_NAME, $encoded, array(
            'expires'  => $expiration,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ) );
        $_COOKIE[ self::COOKIE_NAME ] = $encoded;
    }

    /**
     * Expire consent when necessary (version change or expiration).
     */
    public function maybe_expire_consent() : void {
        if ( is_admin() ) {
            return;
        }

        $settings = $this->settings->get_settings();
        $consent  = $this->get_current_consent();
        $expiry   = isset( $consent['timestamp'] ) ? (int) $consent['timestamp'] + ( (int) $settings['consent_expiration_days'] * DAY_IN_SECONDS ) : 0;

        if ( empty( $consent['version'] ) || $consent['version'] !== $settings['policy_version'] || time() > $expiry ) {
            if ( headers_sent() ) {
                return;
            }
            $path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
            $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

            setcookie( self::COOKIE_NAME, '', array(
                'expires'  => time() - HOUR_IN_SECONDS,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ) );
            unset( $_COOKIE[ self::COOKIE_NAME ] );
        }
    }
}
