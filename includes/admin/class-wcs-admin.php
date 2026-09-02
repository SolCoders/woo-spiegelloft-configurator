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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register top-level menu and dashboard page.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Mirror Builder', 'woo-spiegelloft-configurator' ),
			__( 'Mirror Builder', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-customizer',
			56
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Setup Guide', 'woo-spiegelloft-configurator' ),
			__( 'Setup Guide', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Settings', 'woo-spiegelloft-configurator' ),
			__( 'Settings', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-configurator-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			'wcs_configurator_settings',
			'wcs_configurator_layout',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_layout' ),
				'default'           => 'compact_dropdown',
			)
		);
	}

	/**
	 * Sanitize storefront layout option.
	 *
	 * @param mixed $value Submitted value.
	 */
	public function sanitize_layout( $value ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'compact_dropdown', 'visual_cards' ), true ) ? $value : 'compact_dropdown';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-spiegelloft-configurator' ) );
		}

		$layout = get_option( 'wcs_configurator_layout', 'compact_dropdown' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mirror Builder Settings', 'woo-spiegelloft-configurator' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wcs_configurator_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Configurator layout', 'woo-spiegelloft-configurator' ); ?></th>
						<td>
							<select name="wcs_configurator_layout">
								<option value="compact_dropdown" <?php selected( $layout, 'compact_dropdown' ); ?>><?php esc_html_e( 'Compact dropdown layout', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="visual_cards" <?php selected( $layout, 'visual_cards' ); ?>><?php esc_html_e( 'Visual card layout', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Compact dropdown keeps every option visible in one plain configurator. Visual card layout restores the previous image-card design.', 'woo-spiegelloft-configurator' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
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

		$step_choices_done   = $choices_count >= 1;
		$step_templates_done = $templates_count >= 1;
		$step_products_done  = $products_count >= 1;

		include WCS_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
