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
		add_action( 'wc_ajax_wcs_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_cart_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_meta_styles' ) );
	}

	/**
	 * Add a configured product without leaving the page on validation failure.
	 */
	public function ajax_add_to_cart(): void {
		if ( ! check_ajax_referer( 'wcs_add_to_cart', 'nonce', false ) ) {
			wp_send_json_error( array( 'messages' => array( __( 'Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.', 'woo-spiegelloft-configurator' ) ) ), 403 );
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$quantity   = wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) );
		if ( 'yes' !== get_post_meta( $product_id, '_wcs_configurator_enabled', true ) || $quantity <= 0 ) {
			wp_send_json_error( array( 'messages' => array( __( 'Ungültiges konfiguriertes Produkt oder ungültige Menge.', 'woo-spiegelloft-configurator' ) ) ), 400 );
		}

		// Keep unrelated session notices; return only messages from this attempt.
		$previous_notices = wc_get_notices();
		wc_set_notices( array() );
		$added    = false;
		$messages = array();
		add_filter( 'gettext', array( WCS_Storefront_Text::class, 'woocommerce_message' ), 10, 3 );
		try {
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
			if ( $passed ) {
				$added = (bool) WC()->cart->add_to_cart( $product_id, $quantity );
			}
			if ( $added ) {
				do_action( 'woocommerce_ajax_added_to_cart', $product_id );
			}
		} catch ( Exception $exception ) {
			$messages[] = wp_strip_all_tags( $exception->getMessage() );
		} finally {
			remove_filter( 'gettext', array( WCS_Storefront_Text::class, 'woocommerce_message' ), 10 );
			foreach ( wc_get_notices() as $type => $notices ) {
				if ( 'success' === $type ) {
					continue;
				}
				foreach ( $notices as $notice ) {
					$messages[] = WCS_Storefront_Text::message( wp_strip_all_tags( $notice['notice'] ) );
				}
			}
			wc_set_notices( $previous_notices );
		}

		if ( ! $added ) {
			wp_send_json_error( array( 'messages' => $messages ?: array( __( 'Diese Konfiguration konnte nicht zum Warenkorb hinzugefügt werden. Bitte versuchen Sie es erneut.', 'woo-spiegelloft-configurator' ) ) ) );
		}

		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();
		wp_send_json_success(
			array(
				'messages'  => array_values( array_unique( $messages ) ),
				'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>' ) ),
				'cart_hash' => WC()->cart->get_cart_hash(),
				'redirect'  => 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ? wc_get_cart_url() : '',
			)
		);
	}

	/**
	 * Keep configured cart item data readable when themes flatten WooCommerce meta rows.
	 */
	public function enqueue_cart_meta_styles(): void {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_register_style( 'wcs-cart-item-meta', false, array(), WCS_VERSION );
		wp_enqueue_style( 'wcs-cart-item-meta' );
		wp_add_inline_style(
			'wcs-cart-item-meta',
			'.woocommerce-cart-form .variation dt,.woocommerce-cart-form .variation dd,.woocommerce-checkout-review-order .variation dt,.woocommerce-checkout-review-order .variation dd{display:block;float:none;margin:0 0 3px;padding:0}.woocommerce-cart-form .variation dd p,.woocommerce-checkout-review-order .variation dd p{display:inline;margin:0}.wcs-cart-item-meta__value{display:block}'
		);
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
			wc_add_notice( __( 'Bitte vervollständigen Sie die Auswahl im Konfigurator.', 'woo-spiegelloft-configurator' ), 'error' );
			return false;
		}

		$builder = wcs_get_config_builder();
		if ( ! $builder ) {
			wc_add_notice( __( 'Der Konfigurator ist derzeit nicht verfügbar.', 'woo-spiegelloft-configurator' ), 'error' );
			return false;
		}

		$config = $builder->build_cart_configuration( $product_id, $selections );
		if ( is_wp_error( $config ) ) {
			wc_add_notice( WCS_Storefront_Text::message( $config->get_error_message() ), 'error' );
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
			'template_id' => (int) $config['template_id'],
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
			$cart_item['wcs_configuration']['items'] = $this->remove_container_rows(
				(int) ( $cart_item['product_id'] ?? $values['product_id'] ?? 0 ),
				(array) ( $values['wcs_configuration']['items'] ?? array() ),
				(array) ( $values['wcs_configuration']['selections'] ?? array() )
			);
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
			$template_service = new WCS_Template( new WCS_Cache() );
			$template_id = (int) ( $cart_item['wcs_configuration']['template_id'] ?? $template_service->get_product_template_id( $product->get_id() ) );
			$price   = isset( $cart_item['wcs_configuration']['price'] )
				? (float) $cart_item['wcs_configuration']['price']
				: $builder->calculate_price( (float) $product->get_regular_price(), (array) $cart_item['wcs_configuration']['selections'], $template_service->get_template_data( $template_id ) ?? array() );

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
		$items = $this->remove_container_rows( (int) ( $cart_item['product_id'] ?? 0 ), $items, (array) $cart_item['wcs_configuration']['selections'] );

		foreach ( $items as $item ) {
			$label = WCS_Storefront_Text::label( (string) ( $item['label'] ?? '' ) );
			$value = (string) ( $item['value'] ?? '' );
			$price = (float) ( $item['price'] ?? 0 );
			if ( '' === trim( $label ) || ! WCS_Storefront_Text::filled( $value, empty( $item['customer_text'] ) ) ) {
				continue;
			}
			if ( $price > 0 ) {
				$value .= ' (' . wp_strip_all_tags( wc_price( $price ) ) . ')';
			}
			$item_data[] = array(
				'key'     => esc_html( $label ),
				'value'   => esc_html( $value ),
				'display' => '<span class="wcs-cart-item-meta__value">' . esc_html( $value ) . '</span>',
			);
		}

		return $item_data;
	}

	/** Remove internal parent rows from cart snapshots saved before this fix. */
	private function remove_container_rows( int $product_id, array $items, array $selections ): array {
		if ( $product_id <= 0 || empty( $items ) ) { return $items; }
		$builder = wcs_get_config_builder();
		$config = $builder ? $builder->build_for_product( $product_id ) : null;
		if ( ! is_array( $config ) ) { return $items; }
		foreach ( (array) ( $config['extras'] ?? array() ) as $slug => $group ) {
			$options = (array) ( $group['value'] ?? array() );
			$first = reset( $options );
			if ( empty( $first['customer_fields'] ) || ! isset( $selections[ $slug ] ) ) { continue; }
			$option = $this->find_option( $options, (string) $selections[ $slug ] );
			if ( empty( $option ) ) { continue; }
			$label = (string) ( $group['title'] ?? ucwords( str_replace( '_', ' ', (string) $slug ) ) );
			$value = (string) ( $option['name'] ?? $option['value'] ?? $selections[ $slug ] );
			$items = array_filter( $items, static function ( array $item ) use ( $label, $value ): bool {
				return WCS_Storefront_Text::label( (string) ( $item['label'] ?? '' ) ) !== WCS_Storefront_Text::label( $label ) || (string) ( $item['value'] ?? '' ) !== $value;
			} );
		}
		return array_values( $items );
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
		$dimension_labels = array();
		foreach ( (array) ( $config['side_measurements'] ?? array() ) as $field ) {
			$dimension_labels[ WCS_Pricing::field_key( $field ) ] = WCS_Storefront_Text::label( (string) ( $field['label'] ?? '' ) );
		}
		foreach ( array( 'width', 'height', 'diameter', 'top_width', 'bottom_width', 'left_height', 'right_height' ) as $dimension ) {
			if ( isset( $selections[ $dimension ] ) && '' !== (string) $selections[ $dimension ] ) {
				$items[] = array(
					'label' => WCS_Storefront_Text::label( ucwords( str_replace( '_', ' ', $dimension ) ) ),
					'value' => (string) $selections[ $dimension ] . ' mm',
					'price' => 0,
				);
			}
		}
		foreach ( $selections as $dimension => $value ) {
			$dimension = (string) $dimension;
			if ( 0 === strpos( $dimension, 'side_' ) && '' !== (string) $value ) {
				$items[] = array(
					'label' => $dimension_labels[ $dimension ] ?? WCS_Storefront_Text::label( ucwords( str_replace( '_', ' ', substr( $dimension, 5 ) ) ) ),
					'value' => (string) $value . ' mm',
					'price' => 0,
				);
			}
		}

		$extras = (array) ( $config['extras'] ?? array() );
		foreach ( $extras as $group_slug => $group ) {
			if ( ! WCS_Storefront_Text::filled( $selections[ $group_slug ] ?? '' ) ) {
				continue;
			}

			$option = $this->find_option( (array) ( $group['value'] ?? array() ), (string) $selections[ $group_slug ] );
			if ( empty( $option ) ) {
				continue;
			}
			$customer_items = $this->build_customer_field_items( (string) $group_slug, (string) $selections[ $group_slug ], $option, $selections );
			// A hidden parent selector is a container for its customer fields.
			$first_option = reset( $group['value'] );
			$hidden_parent = ! empty( $first_option['customer_fields'] );
			if ( $hidden_parent && empty( $customer_items ) ) {
				continue;
			}

			if ( ! $hidden_parent ) {
				$items[] = array(
					'label' => (string) ( $group['title'] ?? ucwords( str_replace( '_', ' ', (string) $group_slug ) ) ),
					'value' => (string) ( $option['name'] ?? $option['value'] ?? $selections[ $group_slug ] ),
					'price' => (float) ( $option['price'] ?? 0 ),
				);
			}

			$items = array_merge( $items, $customer_items );

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
			if ( ! WCS_Storefront_Text::filled( $value, 'dropdown' === (string) ( $field['type'] ?? '' ) ) ) {
				continue;
			}

			$display_value = $value;
			$field_option = null;
			$price         = ! empty( $field['price_enabled'] ) && 'dropdown' !== (string) ( $field['type'] ?? '' ) ? (float) ( $field['price'] ?? 0 ) : 0.0;
			if ( 'dropdown' === (string) ( $field['type'] ?? '' ) ) {
				$field_option = $this->find_customer_field_option( (array) ( $field['options'] ?? array() ), $value );
				if ( $field_option ) {
					$display_value = WCS_Storefront_Text::label( (string) ( $field_option['label'] ?? $value ) );
					$price         = ! empty( $field['price_enabled'] ) ? (float) ( $field_option['price'] ?? 0 ) : 0;
				}
			}

			$items[] = array(
				'label' => WCS_Storefront_Text::label( (string) ( $field['label'] ?? $key ) ),
				'value' => $display_value,
				'price' => $price,
				'customer_text' => 'text' === (string) ( $field['type'] ?? '' ),
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
