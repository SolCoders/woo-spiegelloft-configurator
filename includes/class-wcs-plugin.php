<?php
/**
 * Main plugin orchestrator.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Plugin
 */
class WCS_Plugin {

	/**
	 * Run all plugin components.
	 */
	public function run(): void {
		$this->declare_hpos_compatibility();
		$this->register_components();
	}

	/**
	 * Declare HPOS compatibility with WooCommerce.
	 */
	private function declare_hpos_compatibility(): void {
		add_action(
			'before_woocommerce_init',
			static function (): void {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						WCS_PLUGIN_FILE,
						true
					);
				}
			}
		);
	}

	/**
	 * Instantiate and register hooks for all components.
	 */
	private function register_components(): void {
		$cache = new WCS_Cache();

		$field_registry = new WCS_Extra_Field_Type_Registry();
		$field_registry->register_defaults();

		$extras_registry = new WCS_Extras_Registry();
		$extras_registry->init();

		$extras_catalog = new WCS_Extras_Catalog( $cache );
		$extras_catalog->register();

		$template = new WCS_Template( $cache );
		$template->register();

		$validation     = new WCS_Validation_Engine();
		$config_builder = new WCS_Config_Builder( $extras_registry, $extras_catalog, $template, $validation );

		if ( is_admin() ) {
			$admin = new WCS_Admin();
			$admin->register();

			$choice_meta = new WCS_Choice_Meta( $extras_registry, $field_registry );
			$choice_meta->register();

			$extras_admin = new WCS_Extras_Admin( $extras_catalog, $extras_registry );
			$extras_admin->register();

			$template_admin = new WCS_Template_Admin( $template, $extras_registry, $extras_catalog );
			$template_admin->register();

			$product_meta = new WCS_Product_Meta( $template );
			$product_meta->register();
		}

		$rest = new WCS_REST_Controller( $config_builder, $validation );
		$rest->register();

		$storefront = new WCS_Storefront( $config_builder );
		$storefront->register();

		$cart = new WCS_Cart( $validation );
		$cart->register();

		$order = new WCS_Order();
		$order->register();

		do_action( 'wcs_plugin_loaded', $this );
	}
}
