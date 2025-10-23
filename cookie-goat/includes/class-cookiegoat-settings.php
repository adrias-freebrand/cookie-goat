<?php
/**
 * Admin settings for Cookie GOAT plugin.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings handler using Settings API.
 */
class CookieGoat_Settings {
    /**
     * Option name used for plugin configuration.
     */
    public const OPTION_NAME = 'cookiegoat_settings';

    /**
     * Register hooks.
     */
    public function register() : void {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Retrieve settings.
     *
     * @return array<string, mixed>
     */
    public function get_settings() : array {
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
            'category_texts'          => array(
                'necessary'  => __( 'Cookies esenciales para el funcionamiento básico.', 'cookie-goat' ),
                'analytics'  => __( 'Ayudan a comprender cómo interactúan las personas con el sitio.', 'cookie-goat' ),
                'marketing'  => __( 'Se utilizan para mostrar anuncios personalizados.', 'cookie-goat' ),
                'preferences'=> __( 'Permiten recordar tus preferencias.', 'cookie-goat' ),
            ),
        );

        $settings = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Add settings menu.
     */
    public function register_menu() : void {
        add_options_page(
            __( 'Cookie GOAT – CMP', 'cookie-goat' ),
            __( 'Cookie GOAT 🐐', 'cookie-goat' ),
            'manage_options',
            'cookiegoat-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings() : void {
        register_setting( 'cookiegoat_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'cookiegoat_banner_section',
            __( 'Textos del banner', 'cookie-goat' ),
            function () : void {
                echo '<p>' . esc_html__( 'Configura los textos mostrados en el banner y el modal de consentimiento.', 'cookie-goat' ) . '</p>';
            },
            'cookiegoat-settings'
        );

        add_settings_field(
            'banner_title',
            __( 'Título del banner', 'cookie-goat' ),
            array( $this, 'render_text_input' ),
            'cookiegoat-settings',
            'cookiegoat_banner_section',
            array(
                'label_for' => 'banner_title',
                'option'    => 'banner_title',
                'type'      => 'text',
            )
        );

        add_settings_field(
            'banner_description',
            __( 'Descripción', 'cookie-goat' ),
            array( $this, 'render_textarea' ),
            'cookiegoat-settings',
            'cookiegoat_banner_section',
            array(
                'label_for' => 'banner_description',
                'option'    => 'banner_description',
            )
        );

        add_settings_field(
            'floating_button_label',
            __( 'Texto del botón flotante', 'cookie-goat' ),
            array( $this, 'render_text_input' ),
            'cookiegoat-settings',
            'cookiegoat_banner_section',
            array(
                'label_for' => 'floating_button_label',
                'option'    => 'floating_button_label',
                'type'      => 'text',
            )
        );

        add_settings_section(
            'cookiegoat_policy_section',
            __( 'Política y caducidad', 'cookie-goat' ),
            function () : void {
                echo '<p>' . esc_html__( 'Enlaza tu política de cookies y establece la renovación automática del consentimiento.', 'cookie-goat' ) . '</p>';
            },
            'cookiegoat-settings'
        );

        add_settings_field(
            'policy_link',
            __( 'Enlace a la política', 'cookie-goat' ),
            array( $this, 'render_text_input' ),
            'cookiegoat-settings',
            'cookiegoat_policy_section',
            array(
                'label_for' => 'policy_link',
                'option'    => 'policy_link',
                'type'      => 'url',
            )
        );

        add_settings_field(
            'policy_version',
            __( 'Versión del texto legal', 'cookie-goat' ),
            array( $this, 'render_text_input' ),
            'cookiegoat-settings',
            'cookiegoat_policy_section',
            array(
                'label_for' => 'policy_version',
                'option'    => 'policy_version',
                'type'      => 'text',
            )
        );

        add_settings_field(
            'consent_expiration_days',
            __( 'Renovación automática (días)', 'cookie-goat' ),
            array( $this, 'render_number_input' ),
            'cookiegoat-settings',
            'cookiegoat_policy_section',
            array(
                'label_for' => 'consent_expiration_days',
                'option'    => 'consent_expiration_days',
                'min'       => 30,
                'max'       => 730,
            )
        );

        add_settings_section(
            'cookiegoat_behaviour_section',
            __( 'Escaneo y GTM', 'cookie-goat' ),
            function () : void {
                echo '<p>' . esc_html__( 'Configura el escaneo automático y opcionalmente tu contenedor de Google Tag Manager para disparar el consentimiento.', 'cookie-goat' ) . '</p>';
            },
            'cookiegoat-settings'
        );

        add_settings_field(
            'gtm_container_id',
            __( 'Contenedor GTM (opcional)', 'cookie-goat' ),
            array( $this, 'render_text_input' ),
            'cookiegoat-settings',
            'cookiegoat_behaviour_section',
            array(
                'label_for'   => 'gtm_container_id',
                'option'      => 'gtm_container_id',
                'placeholder' => 'GTM-XXXXXXX',
                'type'        => 'text',
            )
        );

        add_settings_field(
            'autoscan_frequency',
            __( 'Frecuencia de escaneo (días)', 'cookie-goat' ),
            array( $this, 'render_number_input' ),
            'cookiegoat-settings',
            'cookiegoat_behaviour_section',
            array(
                'label_for' => 'autoscan_frequency',
                'option'    => 'autoscan_frequency',
                'min'       => DAY_IN_SECONDS,
                'max'       => DAY_IN_SECONDS * 30,
                'step'      => DAY_IN_SECONDS,
                'help'      => __( 'Se recomienda entre 7 y 30 días.', 'cookie-goat' ),
            )
        );

        add_settings_section(
            'cookiegoat_category_section',
            __( 'Descripciones de categorías', 'cookie-goat' ),
            function () : void {
                echo '<p>' . esc_html__( 'Personaliza la descripción que verán los usuarios en la segunda capa.', 'cookie-goat' ) . '</p>';
            },
            'cookiegoat-settings'
        );

        $categories = array(
            'necessary'   => __( 'Esenciales', 'cookie-goat' ),
            'preferences' => __( 'Preferencias', 'cookie-goat' ),
            'analytics'   => __( 'Analíticas', 'cookie-goat' ),
            'marketing'   => __( 'Marketing', 'cookie-goat' ),
        );

        foreach ( $categories as $key => $label ) {
            add_settings_field(
                'category_' . $key,
                sprintf( /* translators: %s: category label */ __( 'Descripción – %s', 'cookie-goat' ), $label ),
                array( $this, 'render_category_textarea' ),
                'cookiegoat-settings',
                'cookiegoat_category_section',
                array(
                    'label_for' => 'category_' . $key,
                    'option'    => $key,
                )
            );
        }
    }

    /**
     * Sanitize plugin settings.
     *
     * @param array<string, mixed> $input Raw settings.
     * @return array<string, mixed>
     */
    public function sanitize_settings( array $input ) : array {
        $output                        = $this->get_settings();
        $output['banner_title']        = isset( $input['banner_title'] ) ? sanitize_text_field( $input['banner_title'] ) : $output['banner_title'];
        $output['banner_description']  = isset( $input['banner_description'] ) ? wp_kses_post( $input['banner_description'] ) : $output['banner_description'];
        $output['policy_link']         = isset( $input['policy_link'] ) ? esc_url_raw( $input['policy_link'] ) : $output['policy_link'];
        $output['policy_version']      = isset( $input['policy_version'] ) ? sanitize_text_field( $input['policy_version'] ) : $output['policy_version'];
        $output['gtm_container_id']    = isset( $input['gtm_container_id'] ) ? strtoupper( sanitize_text_field( $input['gtm_container_id'] ) ) : $output['gtm_container_id'];
        $output['floating_button_label'] = isset( $input['floating_button_label'] ) ? sanitize_text_field( $input['floating_button_label'] ) : $output['floating_button_label'];

        if ( isset( $input['consent_expiration_days'] ) ) {
            $expiration = absint( $input['consent_expiration_days'] );
            if ( $expiration < 30 ) {
                $expiration = 30;
            }
            if ( $expiration > 730 ) {
                $expiration = 730;
            }
            $output['consent_expiration_days'] = $expiration;
        }

        if ( isset( $input['autoscan_frequency'] ) ) {
            $frequency = absint( $input['autoscan_frequency'] );
            if ( $frequency < DAY_IN_SECONDS ) {
                $frequency = DAY_IN_SECONDS;
            }
            if ( $frequency > DAY_IN_SECONDS * 30 ) {
                $frequency = DAY_IN_SECONDS * 30;
            }
            $output['autoscan_frequency'] = $frequency;
        }

        if ( isset( $input['category_texts'] ) && is_array( $input['category_texts'] ) ) {
            foreach ( $input['category_texts'] as $category => $text ) {
                $output['category_texts'][ $category ] = sanitize_textarea_field( $text );
            }
        }

        return $output;
    }

    /**
     * Render text input field.
     *
     * @param array<string, string> $args Arguments.
     */
    public function render_text_input( array $args ) : void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $type     = isset( $args['type'] ) ? $args['type'] : 'text';
        $value    = isset( $settings[ $option ] ) ? $settings[ $option ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

        printf(
            '<input type="%1$s" id="%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text" placeholder="%5$s" />',
            esc_attr( $type ),
            esc_attr( $option ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );
    }

    /**
     * Render textarea field.
     *
     * @param array<string, string> $args Arguments.
     */
    public function render_textarea( array $args ) : void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $value    = isset( $settings[ $option ] ) ? $settings[ $option ] : '';

        printf(
            '<textarea id="%1$s" name="%2$s[%1$s]" rows="4" class="large-text">%3$s</textarea>',
            esc_attr( $option ),
            esc_attr( self::OPTION_NAME ),
            esc_textarea( $value )
        );
    }

    /**
     * Render number input field.
     *
     * @param array<string, mixed> $args Arguments.
     */
    public function render_number_input( array $args ) : void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $value    = isset( $settings[ $option ] ) ? absint( $settings[ $option ] ) : 0;
        $min      = isset( $args['min'] ) ? absint( $args['min'] ) : 0;
        $max      = isset( $args['max'] ) ? absint( $args['max'] ) : 0;
        $step     = isset( $args['step'] ) ? absint( $args['step'] ) : 1;

        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$d" min="%4$d" max="%5$d" step="%6$d" />',
            esc_attr( $option ),
            esc_attr( self::OPTION_NAME ),
            absint( $value ),
            absint( $min ),
            absint( $max ),
            absint( $step )
        );

        if ( isset( $args['help'] ) ) {
            echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
        }
    }

    /**
     * Render textarea for categories.
     *
     * @param array<string, string> $args Arguments.
     */
    public function render_category_textarea( array $args ) : void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $value    = isset( $settings['category_texts'][ $option ] ) ? $settings['category_texts'][ $option ] : '';

        printf(
            '<textarea id="%1$s" name="%2$s[category_texts][%3$s]" rows="3" class="large-text">%4$s</textarea>',
            esc_attr( 'category_' . $option ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $option ),
            esc_textarea( $value )
        );
    }

    /**
     * Render settings page content.
     */
    public function render_settings_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        $notice   = isset( $_GET['cookiegoat_scan'] ) ? sanitize_text_field( wp_unslash( $_GET['cookiegoat_scan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Cookie GOAT 🐐 – Ajustes', 'cookie-goat' ); ?></h1>
            <p><?php echo esc_html__( 'Configura el banner, el modal de preferencias, el escaneo automático y la integración con Google Consent Mode v2.', 'cookie-goat' ); ?></p>
            <?php if ( 'success' === $notice ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'El escaneo se ejecutó correctamente. Revisa la tabla de la política para ver los resultados actualizados.', 'cookie-goat' ); ?></p>
                </div>
            <?php endif; ?>
            <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
                <?php
                settings_fields( 'cookiegoat_settings_group' );
                do_settings_sections( 'cookiegoat-settings' );
                submit_button( __( 'Guardar cambios', 'cookie-goat' ) );
                ?>
            </form>
            <hr />
            <h2><?php echo esc_html__( 'Herramientas', 'cookie-goat' ); ?></h2>
            <p><?php echo esc_html__( 'Ejecuta un escaneo inmediato para actualizar la tabla de cookies y la política.', 'cookie-goat' ); ?></p>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <?php wp_nonce_field( 'cookiegoat_manual_scan', 'cookiegoat_nonce' ); ?>
                <input type="hidden" name="action" value="cookiegoat_manual_scan" />
                <?php submit_button( __( 'Ejecutar escaneo ahora', 'cookie-goat' ), 'secondary' ); ?>
            </form>
            <p class="description">
                <?php
                if ( ! empty( $settings['autoscan_last_run'] ) ) {
                    echo esc_html( sprintf( /* translators: %s: date */ __( 'Último escaneo automático: %s', 'cookie-goat' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $settings['autoscan_last_run'] ) ) );
                } else {
                    echo esc_html__( 'Aún no se ha ejecutado un escaneo automático.', 'cookie-goat' );
                }
                ?>
            </p>
        </div>
        <?php
    }
}
