<?php
/**
 * Policy helper and shortcodes.
 *
 * @package Cookie_Goat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides assistance for cookie policy generation.
 */
class CookieGoat_Policy {
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
        add_shortcode( 'cookiegoat_policy_table', array( $this, 'render_policy_table' ) );
    }

    /**
     * Render cookie policy table shortcode.
     *
     * @return string
     */
    public function render_policy_table() : string {
        $scan = get_option( CookieGoat_Scanner::RESULTS_OPTION, array() );
        if ( empty( $scan['cookies'] ) || ! is_array( $scan['cookies'] ) ) {
            return '<p>' . esc_html__( 'Aún no hay datos de cookies disponibles. Ejecuta un escaneo desde los ajustes de Cookie GOAT.', 'cookie-goat' ) . '</p>';
        }

        ob_start();
        ?>
        <table class="cookiegoat-policy-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Nombre', 'cookie-goat' ); ?></th>
                    <th><?php esc_html_e( 'Proveedor', 'cookie-goat' ); ?></th>
                    <th><?php esc_html_e( 'Finalidad', 'cookie-goat' ); ?></th>
                    <th><?php esc_html_e( 'Duración', 'cookie-goat' ); ?></th>
                    <th><?php esc_html_e( 'Categoría', 'cookie-goat' ); ?></th>
                    <th><?php esc_html_e( 'Revocación', 'cookie-goat' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $scan['cookies'] as $cookie ) :
                    $category = isset( $cookie['category'] ) ? $cookie['category'] : 'necessary';
                    $categories = $this->settings->get_settings()['category_texts'];
                    $revocation = __( 'Puedes modificar o retirar tu consentimiento en cualquier momento desde el botón de preferencias o escribiendo a nuestro DPO.', 'cookie-goat' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $cookie['name'] ); ?></td>
                        <td><?php echo esc_html( $cookie['provider'] ); ?></td>
                        <td><?php echo esc_html( $cookie['purpose'] ); ?></td>
                        <td><?php echo esc_html( $cookie['duration'] ); ?></td>
                        <td><?php echo esc_html( ucfirst( $category ) ); ?></td>
                        <td><?php echo esc_html( $revocation ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return (string) ob_get_clean();
    }
}
