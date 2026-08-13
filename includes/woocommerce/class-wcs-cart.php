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

		$builder = wcs_get_config_builder();
		if ( ! $builder ) {
			wc_add_notice( __( 'Configurator is not available right now.', 'woo-spiegelloft-configurator' ), 'error' );
			return false;
		}

		$config = $builder->build_cart_configuration( $product_id, $selections );
		if ( is_wp_error( $config ) ) {
			wc_add_notice( $config->get_error_message(), 'error' );
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

		$builder = wcs_get_config_builder();
		if ( ! $builder ) {
			return $cart_item_data;
		}

		$config = $builder->build_cart_configuration( $product_id, $selections );
		if ( is_wp_error( $config ) ) {
			return $cart_item_data;
		}

		$selections = (array) ( $config['selections'] ?? array() );

		$cart_item_data['wcs_configuration'] = array(
			'selections' => $selections,
			'items'      => $this->build_display_items( $product_id, $selections ),
			'price'      => (float) ( $config['price'] ?? 0 ),
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
			$product = $cart_item['data'];
			$price   = isset( $cart_item['wcs_configuration']['price'] )
				? (float) $cart_item['wcs_configuration']['price']
				: $builder->calculate_price( (float) $product->get_regular_price(), (array) $cart_item['wcs_configuration']['selections'] );

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

		$items = (array) ( $cart_item['wcs_configuration']['items'] ?? array() );
		if ( empty( $items ) ) {
			$items = $this->build_display_items( (int) ( $cart_item['product_id'] ?? 0 ), (array) $cart_item['wcs_configuration']['selections'] );
		}

		foreach ( $items as $item ) {
			$label = (string) ( $item['label'] ?? '' );
			$value = (string) ( $item['value'] ?? '' );
			$price = (float) ( $item['price'] ?? 0 );
			if ( '' === $label || '' === $value ) {
				continue;
			}
			if ( $price > 0 ) {
				$value .= ' (' . wp_strip_all_tags( wc_price( $price ) ) . ')';
			}
			$item_data[] = array(
				'key'   => esc_html( $label ),
				'value' => esc_html( $value ),
			);
		}

		return $item_data;
	}

	/**
	 * Build human-readable cart meta rows from the active product config.
	 *
	 * @param int                  $product_id  Product ID.
	 * @param array<string, mixed> $selections  Sanitized selections.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_display_items( int $product_id, array $selections ): array {
		$builder = wcs_get_config_builder();
		if ( ! $builder ) {
			return array();
		}

		$config = $builder->build_for_product( $product_id );
		if ( is_wp_error( $config ) ) {
			return array();
		}

		$items = array();
		foreach ( array( 'width', 'height', 'diameter', 'top_width', 'bottom_width', 'left_height', 'right_height' ) as $dimension ) {
			if ( isset( $selections[ $dimension ] ) && '' !== (string) $selections[ $dimension ] ) {
				$items[] = array(
					'label' => ucwords( str_replace( '_', ' ', $dimension ) ),
					'value' => (string) $selections[ $dimension ] . ' mm',
					'price' => 0,
				);
			}
		}

		$extras = (array) ( $config['extras'] ?? array() );
		foreach ( $extras as $group_slug => $group ) {
			if ( empty( $selections[ $group_slug ] ) ) {
				continue;
			}

			$option = $this->find_option( (array) ( $group['value'] ?? array() ), (string) $selections[ $group_slug ] );
			if ( empty( $option ) ) {
				continue;
			}

			$items[] = array(
				'label' => (string) ( $group['title'] ?? ucwords( str_replace( '_', ' ', (string) $group_slug ) ) ),
				'value' => (string) ( $option['name'] ?? $option['value'] ?? $selections[ $group_slug ] ),
				'price' => (float) ( $option['price'] ?? 0 ),
			);

			$items = array_merge( $items, $this->build_customer_field_items( (string) $group_slug, (string) $selections[ $group_slug ], $option, $selections ) );

			$position_key = (string) $group_slug . '_position';
			if ( ! empty( $selections[ $position_key ] ) ) {
				$items[] = array(
					'label' => (string) ( $group['position_config']['label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ),
					'value' => $this->find_position_label( (array) ( $group['position_config']['options'] ?? array() ), (string) $selections[ $position_key ] ),
					'price' => 0,
				);
			}
		}

		return $items;
	}

	/**
	 * Find a configured option by slug.
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @param string                           $slug    Selected slug.
	 * @return array<string, mixed>
	 */
	private function find_option( array $options, string $slug ): array {
		foreach ( $options as $option ) {
			if ( (string) ( $option['value'] ?? '' ) === $slug ) {
				return $option;
			}
		}
		return array();
	}

	/**
	 * Resolve a saved position value to its display label.
	 *
	 * @param array<int, array<string, string>> $positions Position options.
	 * @param string                            $value     Saved value.
	 */
	private function find_position_label( array $positions, string $value ): string {
		foreach ( $positions as $position ) {
			if ( (string) ( $position['value'] ?? '' ) === $value ) {
				return (string) ( $position['label'] ?? $value );
			}
		}
		return $value;
	}

	/**
	 * Build readable rows for conditional customer fields.
	 *
	 * @param string               $group_slug Group slug.
	 * @param string               $selected   Selected option slug.
	 * @param array<string, mixed> $option     Selected option payload.
	 * @param array<string, mixed> $selections Sanitized selections.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_customer_field_items( string $group_slug, string $selected, array $option, array $selections ): array {
		return $this->build_customer_field_row_items( (array) ( $option['customer_fields'] ?? array() ), $group_slug . '.' . $selected, $selections );
	}

	/**
	 * Build readable rows for customer fields recursively.
	 *
	 * @param array<int, array<string, mixed>> $fields     Customer fields.
	 * @param string                           $base_path  Submitted key prefix.
	 * @param array<string, mixed>             $selections Sanitized selections.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_customer_field_row_items( array $fields, string $base_path, array $selections ): array {
		$items = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$key   = $base_path . '.' . (string) ( $field['key'] ?? '' );
			$value = (string) ( $selections[ $key ] ?? '' );
			if ( '' === $value ) {
				continue;
			}

			$display_value = $value;
			$price         = ! empty( $field['price_enabled'] ) && 'dropdown' !== (string) ( $field['type'] ?? '' ) ? (float) ( $field['price'] ?? 0 ) : 0.0;
			if ( 'dropdown' === (string) ( $field['type'] ?? '' ) ) {
				$field_option = $this->find_customer_field_option( (array) ( $field['options'] ?? array() ), $value );
				if ( $field_option ) {
					$display_value = (string) ( $field_option['label'] ?? $value );
					$price         = ! empty( $field['price_enabled'] ) ? (float) ( $field_option['price'] ?? 0 ) : 0;
				}
			}

			$items[] = array(
				'label' => (string) ( $field['label'] ?? $key ),
				'value' => $display_value,
				'price' => $price,
			);

			if ( ! empty( $field_option['customer_fields'] ) && is_array( $field_option['customer_fields'] ) ) {
				$items = array_merge( $items, $this->build_customer_field_row_items( (array) $field_option['customer_fields'], $key . '.' . $value, $selections ) );
			}
		}

		return $items;
	}

	/**
	 * Find a selected customer-field dropdown option.
	 *
	 * @param array<int, array<string, mixed>> $options Field options.
	 * @param string                           $value   Selected value.
	 * @return array<string, mixed>|null
	 */
	private function find_customer_field_option( array $options, string $value ): ?array {
		foreach ( $options as $option ) {
			if ( (string) ( $option['value'] ?? '' ) === $value ) {
				return $option;
			}
		}

		return null;
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
