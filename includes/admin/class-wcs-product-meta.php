<?php
/**
 * WooCommerce product configurator tab.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Product_Meta
 */
class WCS_Product_Meta {

	/**
	 * Template helper.
	 *
	 * @var WCS_Template
	 */
	private WCS_Template $template;

	/**
	 * Constructor.
	 *
	 * @param WCS_Template $template Template helper.
	 */
	public function __construct( WCS_Template $template ) {
		$this->template = $template;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
	}

	/**
	 * Add configurator product data tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_product_tab( array $tabs ): array {
		$tabs['wcs_configurator'] = array(
			'label'    => __( 'Mirror Customizer', 'woo-spiegelloft-configurator' ),
			'target'   => 'wcs_configurator_product_data',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render configurator product panel.
	 */
	public function render_product_panel(): void {
		global $post;

		$product_id  = (int) $post->ID;
		$template_id = $this->template->get_product_template_id( $product_id );
		$templates   = $this->template->get_all_templates();
		$enabled     = 'yes' === get_post_meta( $product_id, '_wcs_enabled', true );

		if ( ! $enabled && 'yes' === get_post_meta( $product_id, '_wcs_configurator_enabled', true ) ) {
			$enabled = true;
		}

		include WCS_PLUGIN_DIR . 'templates/admin/product-configurator-tab.php';
	}

	/**
	 * Save product configurator meta.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_meta( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['_wcs_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wcs_enabled', $enabled );
		update_post_meta( $post_id, '_wcs_configurator_enabled', $enabled );

		if ( isset( $_POST['_wcs_template_id'] ) ) {
			$template_id = absint( $_POST['_wcs_template_id'] );
			$this->template->set_product_template_id( $post_id, $template_id );
		}
	}
}
