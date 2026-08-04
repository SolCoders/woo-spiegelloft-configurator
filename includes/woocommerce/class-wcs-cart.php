<?php
/**
 * WooCommerce cart integration.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Cart
 */
class WCS_Cart {

	/**
	 * Validation engine.
	 *
	 * @var WCS_Validation_Engine
	 */
	private WCS_Validation_Engine $validation;

	/**
	 * Constructor.
	 *
	 * @param WCS_Validation_Engine $validation Validation engine.
	 */
	public function __construct( WCS_Validation_Engine $validation ) {
		$this->validation = $validation;
	}

	/**
	 * Register cart hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_cart_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
	}

	/**
	 * Validate add to cart for configured products.
	 *
	 * @param bool $passed     Validation passed.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( bool $passed, int $product_id, int $quantity ): bool {
		unset( $quantity );

		if ( 'yes' !== get_post_meta( $product_id, '_wcs_configurator_enabled', true ) ) {
			return $passed;
		}

		$selections_raw = isset( $_POST['wcs_selections'] ) ? wp_unslash( $_POST['wcs_selections'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selections     = json_decode( is_string( $selections_raw ) ? $selections_raw : '', true );

		if ( ! is_array( $selections ) || empty( $selections ) ) {
			wc_add_notice( __( 'Please complete the configurator selections.', 'woo-spiegelloft-configurator' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add configuration data to cart item.
	 *
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 * @param int                  $product_id     Product ID.
	 * @param int                  $variation_id   Variation ID.
	 * @return array<string, mixed>
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		unset( $variation_id );

		if ( 'yes' !== get_post_meta( $product_id, '_wcs_configurator_enabled', true ) ) {
			return $cart_item_data;
		}

		$selections_raw = isset( $_POST['wcs_selections'] ) ? wp_unslash( $_POST['wcs_selections'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selections     = json_decode( is_string( $selections_raw ) ? $selections_raw : '', true );

		if ( ! is_array( $selections ) ) {
			return $cart_item_data;
		}

		$selections = $this->validation->sanitize_selections( $selections );

		$cart_item_data['wcs_configuration'] = array(
			'selections' => $selections,
			'unique_key' => md5( wp_json_encode( $selections ) . microtime( true ) ),
		);

		return $cart_item_data;
	}

	/**
	 * Restore cart item configuration from session.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param array<string, mixed> $values    Session values.
	 * @return array<string, mixed>
	 */
	public function get_cart_item_from_session( array $cart_item, array $values ): array {
		if ( isset( $values['wcs_configuration'] ) ) {
			$cart_item['wcs_configuration'] = $values['wcs_configuration'];
		}
		return $cart_item;
	}

	/**
	 * Adjust configured product prices in cart.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function adjust_cart_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$builder = wcs_get_config_builder();
		if ( ! $builder ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['wcs_configuration']['selections'] ) || ! isset( $cart_item['data'] ) ) {
				continue;
			}

			/** @var WC_Product $product */
			$product    = $cart_item['data'];
			$product_id = (int) ( $cart_item['product_id'] ?? 0 );
			$selections = (array) $cart_item['wcs_configuration']['selections'];
			$price      = $builder->calculate_price( (float) $product->get_regular_price(), $selections );

			$product->set_price( $price );
		}
	}

	/**
	 * Display configuration in cart.
	 *
	 * @param array<int, array<string, string>> $item_data Item data rows.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['wcs_configuration']['selections'] ) ) {
			return $item_data;
		}

		foreach ( (array) $cart_item['wcs_configuration']['selections'] as $group => $value ) {
			$display = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			$item_data[] = array(
				'key'   => ucwords( str_replace( '_', ' ', (string) $group ) ),
				'value' => esc_html( $display ),
			);
		}

		return $item_data;
	}
}

/**
 * Get config builder instance (lazy helper for cart pricing).
 *
 * @return WCS_Config_Builder|null
 */
function wcs_get_config_builder(): ?WCS_Config_Builder {
	static $builder = null;
	if ( null !== $builder ) {
		return $builder;
	}

	if ( ! class_exists( 'WCS_Config_Builder' ) ) {
		return null;
	}

	$cache    = new WCS_Cache();
	$registry = new WCS_Extras_Registry();
	$registry->load_groups();
	$catalog    = new WCS_Extras_Catalog( $cache );
	$template   = new WCS_Template( $cache );
	$validation = new WCS_Validation_Engine();
	$builder    = new WCS_Config_Builder( $registry, $catalog, $template, $validation );

	return $builder;
}