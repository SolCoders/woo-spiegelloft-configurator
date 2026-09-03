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
		add_action( 'admin_menu', array( $this, 'register_settings_menu' ), 99 );
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

	}

	/**
	 * Register settings submenu after other plugin submenus.
	 */
	public function register_settings_menu(): void {
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
				<section class="wcs-settings-panel">
					<h2><?php esc_html_e( 'Configurator layout', 'woo-spiegelloft-configurator' ); ?></h2>
					<p><?php esc_html_e( 'Choose how customers select mirror options on the product page.', 'woo-spiegelloft-configurator' ); ?></p>
					<div class="wcs-layout-options">
						<label class="wcs-layout-card">
							<input type="radio" name="wcs_configurator_layout" value="compact_dropdown" <?php checked( $layout, 'compact_dropdown' ); ?>>
							<span class="wcs-layout-card__preview wcs-layout-card__preview--compact" aria-hidden="true">
								<span></span><span></span><span></span><span></span>
							</span>
							<span class="wcs-layout-card__body">
								<strong><?php esc_html_e( 'Compact dropdown layout', 'woo-spiegelloft-configurator' ); ?></strong>
								<small><?php esc_html_e( 'Plain rows with labels on one side and dropdown fields on the other.', 'woo-spiegelloft-configurator' ); ?></small>
							</span>
						</label>
						<label class="wcs-layout-card">
							<input type="radio" name="wcs_configurator_layout" value="visual_cards" <?php checked( $layout, 'visual_cards' ); ?>>
							<span class="wcs-layout-card__preview wcs-layout-card__preview--cards" aria-hidden="true">
								<span></span><span></span><span></span><span></span>
							</span>
							<span class="wcs-layout-card__body">
								<strong><?php esc_html_e( 'Visual card layout', 'woo-spiegelloft-configurator' ); ?></strong>
								<small><?php esc_html_e( 'Previous design with large image cards for visual choices.', 'woo-spiegelloft-configurator' ); ?></small>
							</span>
						</label>
					</div>
				</section>
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
