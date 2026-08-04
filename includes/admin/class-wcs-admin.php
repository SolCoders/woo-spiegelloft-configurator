<?php
/**
 * Top-level admin menu.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Admin
 */
class WCS_Admin {

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register top-level menu and dashboard page.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Mirror Configurator', 'woo-spiegelloft-configurator' ),
			__( 'Configurator', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-customizer',
			56
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-spiegelloft-configurator' ) );
		}

		$templates_count = wp_count_posts( 'wcs_template' );
		$options_count   = wp_count_posts( 'wcs_extra_option' );

		include WCS_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}