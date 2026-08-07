<?php
/**
 * WooCommerce order integration.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Order
 */
class WCS_Order {

	/**
	 * Register order hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_order_item_meta_start', array( $this, 'display_order_item_meta' ), 10, 3 );
	}

	/**
	 * Persist configuration on order line items.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public function add_order_item_meta( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $order );

		if ( empty( $values['wcs_configuration'] ) ) {
			return;
		}

		$config = $values['wcs_configuration'];
		$item->add_meta_data( '_wcs_configuration', wp_json_encode( $config ), true );

		if ( ! empty( $config['items'] ) && is_array( $config['items'] ) ) {
			foreach ( $config['items'] as $config_item ) {
				$label = (string) ( $config_item['label'] ?? '' );
				$value = (string) ( $config_item['value'] ?? '' );
				$price = (float) ( $config_item['price'] ?? 0 );
				if ( '' === $label || '' === $value ) {
					continue;
				}
				if ( $price > 0 ) {
					$value .= ' (' . wp_strip_all_tags( wc_price( $price ) ) . ')';
				}
				$item->add_meta_data(
					$label,
					$value,
					true
				);
			}
		}
	}

	/**
	 * Display configuration on order details (frontend).
	 *
	 * @param int                   $item_id Item ID.
	 * @param WC_Order_Item_Product $item    Order item.
	 * @param WC_Order              $order   Order.
	 */
	public function display_order_item_meta( int $item_id, WC_Order_Item_Product $item, WC_Order $order ): void {
		unset( $item_id, $order );

		$raw = $item->get_meta( '_wcs_configuration', true );
		if ( empty( $raw ) ) {
			return;
		}

		$config = json_decode( (string) $raw, true );
		if ( ! is_array( $config ) || ( empty( $config['items'] ) && empty( $config['selections'] ) ) ) {
			return;
		}

		echo '<dl class="wcs-order-configuration">';
		foreach ( (array) ( $config['items'] ?? array() ) as $config_item ) {
			$label = (string) ( $config_item['label'] ?? '' );
			$value = (string) ( $config_item['value'] ?? '' );
			$price = (float) ( $config_item['price'] ?? 0 );
			if ( '' === $label || '' === $value ) {
				continue;
			}
			if ( $price > 0 ) {
				$value .= ' (' . wp_strip_all_tags( wc_price( $price ) ) . ')';
			}
			echo '<dt>' . esc_html( $label ) . '</dt>';
			echo '<dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl>';
	}
}
