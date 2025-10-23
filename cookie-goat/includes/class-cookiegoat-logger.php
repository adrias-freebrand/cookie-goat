<?php
/**
 * Consent logger for Cookie GOAT.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles consent logs stored in custom table.
 */
class CookieGoat_Logger {
    /**
     * Settings handler.
     *
     * @var CookieGoat_Settings
     */
    private CookieGoat_Settings $settings;

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
        add_action( 'admin_menu', array( $this, 'register_log_page' ) );
    }

    /**
     * Insert new log entry.
     *
     * @param array<string, mixed> $consent Consent data.
     */
    public function log_consent( array $consent ) : void {
        global $wpdb;

        $table = $wpdb->prefix . 'cookiegoat_consent_log';
        $ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $hash  = ! empty( $ip ) ? hash( 'sha256', $ip . wp_salt( 'cookiegoat' ) ) : '';

        $wpdb->insert(
            $table,
            array(
                'consent_time'  => current_time( 'mysql' ),
                'user_id'       => get_current_user_id(),
                'hashed_ip'     => $hash,
                'decision'      => wp_json_encode( $consent ),
                'policy_version'=> sanitize_text_field( $this->settings->get_settings()['policy_version'] ),
            ),
            array( '%s', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Register admin log page.
     */
    public function register_log_page() : void {
        add_submenu_page(
            'tools.php',
            __( 'Registro de consentimientos', 'cookie-goat' ),
            __( 'Consentimientos CMP', 'cookie-goat' ),
            'manage_options',
            'cookiegoat-log',
            array( $this, 'render_log_page' )
        );
    }

    /**
     * Render log table.
     */
    public function render_log_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'cookiegoat_consent_log';
        $per_page = 20;
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $offset   = ( $paged - 1 ) * $per_page;

        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY consent_time DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $pages = (int) ceil( $total / $per_page );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Registro de consentimientos', 'cookie-goat' ); ?></h1>
            <p><?php esc_html_e( 'Descarga las evidencias de consentimiento. El hash de IP permite verificar sin almacenar datos personales directos.', 'cookie-goat' ); ?></p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Fecha', 'cookie-goat' ); ?></th>
                        <th><?php esc_html_e( 'Usuario', 'cookie-goat' ); ?></th>
                        <th><?php esc_html_e( 'Hash IP', 'cookie-goat' ); ?></th>
                        <th><?php esc_html_e( 'Resumen', 'cookie-goat' ); ?></th>
                        <th><?php esc_html_e( 'Detalle por categoría', 'cookie-goat' ); ?></th>
                        <th><?php esc_html_e( 'Versión legal', 'cookie-goat' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e( 'No hay consentimientos registrados todavía.', 'cookie-goat' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $items as $item ) :
                            $decision = json_decode( (string) $item['decision'], true );
                            $user     = $item['user_id'] ? get_userdata( (int) $item['user_id'] ) : null;
                            $user_name = $user ? $user->user_login : __( 'Visitante', 'cookie-goat' );
                            ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $item['consent_time'] ) ) ); ?></td>
                                <td><?php echo esc_html( $user_name ); ?></td>
                                <td><code><?php echo esc_html( substr( (string) $item['hashed_ip'], 0, 16 ) ); ?></code></td>
                                <td><?php echo esc_html( isset( $decision['overall'] ) ? ucfirst( (string) $decision['overall'] ) : __( 'N/D', 'cookie-goat' ) ); ?></td>
                                <td>
                                    <?php if ( is_array( $decision ) && isset( $decision['categories'] ) ) : ?>
                                        <ul>
                                            <?php foreach ( $decision['categories'] as $cat => $value ) : ?>
                                                <li><?php echo esc_html( sprintf( '%s: %s', ucfirst( $cat ), $value ? __( 'Permitido', 'cookie-goat' ) : __( 'Denegado', 'cookie-goat' ) ) ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <code><?php echo esc_html( (string) $item['decision'] ); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( (string) $item['policy_version'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages">
                    <?php
                    echo wp_kses_post( paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => __( '&laquo; Anterior', 'cookie-goat' ),
                        'next_text' => __( 'Siguiente &raquo;', 'cookie-goat' ),
                        'total'     => $pages,
                        'current'   => $paged,
                    ) ) );
                    ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
