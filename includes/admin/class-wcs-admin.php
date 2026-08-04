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
			__( 'Mirror Customizer', 'woo-spiegelloft-configurator' ),
			__( 'Mirror Customizer', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-customizer',
			56
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Getting Started', 'woo-spiegelloft-configurator' ),
			__( 'Getting Started', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Render dashboard wizard page.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-spiegelloft-configurator' ) );
		}

		$choices_count   = (int) ( wp_count_posts( 'wcs_extra_option' )->publish ?? 0 );
		$templates_count = (int) ( wp_count_posts( 'wcs_template' )->publish ?? 0 );
		$products_count  = (int) count(
			get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_key'       => '_wcs_enabled',
					'meta_value'     => 'yes',
					'fields'         => 'ids',
				)
			)
		);

		$step_choices_done   = $choices_count >= 10;
		$step_templates_done = $templates_count >= 1;
		$step_products_done  = $products_count >= 1;

		include WCS_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
