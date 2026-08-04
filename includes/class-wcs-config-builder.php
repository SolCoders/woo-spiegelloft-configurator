<?php
/**
 * Builds Shopify-compatible configuration JSON.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Config_Builder
 */
class WCS_Config_Builder {

	/**
	 * Extras registry.
	 *
	 * @var WCS_Extras_Registry
	 */
	private WCS_Extras_Registry $extras_registry;

	/**
	 * Extras catalog.
	 *
	 * @var WCS_Extras_Catalog
	 */
	private WCS_Extras_Catalog $extras_catalog;

	/**
	 * Template helper.
	 *
	 * @var WCS_Template
	 */
	private WCS_Template $template;

	/**
	 * Validation engine.
	 *
	 * @var WCS_Validation_Engine
	 */
	private WCS_Validation_Engine $validation;

	/**
	 * Constructor.
	 *
	 * @param WCS_Extras_Registry   $extras_registry Extras registry.
	 * @param WCS_Extras_Catalog  $extras_catalog  Extras catalog.
	 * @param WCS_Template          $template        Template helper.
	 * @param WCS_Validation_Engine $validation      Validation engine.
	 */
	public function __construct(
		WCS_Extras_Registry $extras_registry,
		WCS_Extras_Catalog $extras_catalog,
		WCS_Template $template,
		WCS_Validation_Engine $validation
	) {
		$this->extras_registry = $extras_registry;
		$this->extras_catalog  = $extras_catalog;
		$this->template        = $template;
		$this->validation      = $validation;
	}

	/**
	 * Build full config payload for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function build_for_product( int $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'wcs_invalid_product', __( 'Invalid product.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		$template_id = $this->template->get_product_template_id( $product_id );
		if ( $template_id <= 0 ) {
			return new WP_Error( 'wcs_no_template', __( 'No configurator template assigned.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		$template_data = $this->template->get_template_data( $template_id );
		if ( ! $template_data ) {
			return new WP_Error( 'wcs_template_not_found', __( 'Template not found.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		return $this->build_payload( $product, $template_data );
	}

	/**
	 * Build Shopify-compatible $data structure.
	 *
	 * @param WC_Product           $product       Product.
	 * @param array<string, mixed> $template_data Template data.
	 * @return array<string, mixed>
	 */
	public function build_payload( WC_Product $product, array $template_data ): array {
		$groups       = $this->extras_registry->get_groups();
		$enabled      = (array) ( $template_data['groups'] ?? array() );
		$group_output = array();

		foreach ( $enabled as $group_slug ) {
			$group_slug = (string) $group_slug;
			$definition = $groups[ $group_slug ] ?? null;
			if ( ! $definition ) {
				continue;
			}

			$options = $this->extras_catalog->get_options_by_group( $group_slug );
			$group_output[ $group_slug ] = array(
				'slug'        => $group_slug,
				'label'       => (string) ( $definition['label'] ?? $group_slug ),
				'input_type'  => (string) ( $definition['input_type'] ?? 'single' ),
				'fields'      => (array) ( $definition['fields'] ?? array() ),
				'options'     => array_map( array( $this, 'format_option_for_json' ), $options ),
				'position'    => (int) ( $definition['position'] ?? 0 ),
				'required'    => (bool) ( $definition['required'] ?? false ),
			);
		}

		uasort(
			$group_output,
			static function ( array $a, array $b ): int {
				return ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 );
			}
		);

		$data = array(
			'product_id'   => $product->get_id(),
			'product_sku'  => $product->get_sku(),
			'template_id'  => (int) ( $template_data['id'] ?? 0 ),
			'base_price'   => (float) $product->get_price(),
			'currency'     => get_woocommerce_currency(),
			'groups'       => $group_output,
			'rules'        => (array) ( $template_data['rules'] ?? array() ),
			'meta'         => (array) ( $template_data['meta'] ?? array() ),
			'generated_at' => gmdate( 'c' ),
		);

		/**
		 * Filter built config payload.
		 *
		 * @param array<string, mixed> $data    Config data.
		 * @param WC_Product           $product Product.
		 */
		return apply_filters( 'wcs_build_config_payload', $data, $product );
	}

	/**
	 * Format catalog option for JSON output.
	 *
	 * @param array<string, mixed> $option Option data.
	 * @return array<string, mixed>
	 */
	private function format_option_for_json( array $option ): array {
		$meta = (array) ( $option['meta'] ?? array() );
		return array(
			'id'    => (int) ( $option['id'] ?? 0 ),
			'title' => (string) ( $option['title'] ?? '' ),
			'slug'  => (string) ( $option['slug'] ?? '' ),
			'price' => isset( $meta['_wcs_price'] ) ? (float) $meta['_wcs_price'] : 0.0,
			'image' => (string) ( $meta['_wcs_image'] ?? '' ),
			'meta'  => $meta,
		);
	}

	/**
	 * Calculate total price for selections.
	 *
	 * @param float                $base_price Base product price.
	 * @param array<string, mixed> $selections User selections (group => option slug or array).
	 * @return float
	 */
	public function calculate_price( float $base_price, array $selections ): float {
		$total = $base_price;

		foreach ( $selections as $group_slug => $selected ) {
			$options = $this->extras_catalog->get_options_by_group( (string) $group_slug );
			$slugs   = is_array( $selected ) ? $selected : array( $selected );

			foreach ( $slugs as $slug ) {
				foreach ( $options as $option ) {
					if ( ( $option['slug'] ?? '' ) === (string) $slug ) {
						$price = (float) ( $option['meta']['_wcs_price'] ?? 0 );
						$total += $price;
					}
				}
			}
		}

		return (float) apply_filters( 'wcs_calculate_configured_price', $total, $base_price, $selections );
	}

	/**
	 * Validate and build cart line item meta.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $selections Selections.
	 * @return array<string, mixed>|WP_Error
	 */
	public function build_cart_configuration( int $product_id, array $selections ) {
		$template_id = $this->template->get_product_template_id( $product_id );
		$template    = $this->template->get_template_data( $template_id );
		if ( ! $template ) {
			return new WP_Error( 'wcs_no_template', __( 'No template assigned.', 'woo-spiegelloft-configurator' ) );
		}

		$selections = $this->validation->sanitize_selections( $selections );
		$valid      = $this->validation->validate( $selections, $template, $this->extras_registry->get_groups() );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'wcs_invalid_product', __( 'Invalid product.', 'woo-spiegelloft-configurator' ) );
		}

		$price = $this->calculate_price( (float) $product->get_price(), $selections );

		return array(
			'selections'  => $selections,
			'template_id' => $template_id,
			'price'       => $price,
		);
	}
}