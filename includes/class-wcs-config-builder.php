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
	 * @param WCS_Extras_Catalog    $extras_catalog  Extras catalog.
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
		$groups         = $this->extras_registry->get_groups();
		$enabled        = (array) ( $template_data['enabled_groups'] ?? $template_data['groups'] ?? array() );
		$option_map     = (array) ( $template_data['extra_option_map'] ?? array() );
		$dimensions     = (array) ( $template_data['dimensions'] ?? array() );
		$edge_override  = (array) ( $template_data['edge_override'] ?? array() );
		$extras_output  = array();

		foreach ( $enabled as $group_slug ) {
			$group_slug = (string) $group_slug;
			$definition = $groups[ $group_slug ] ?? null;
			if ( ! $definition ) {
				continue;
			}

			if ( 'static' === ( $definition['type'] ?? 'selectable' ) && 'edge' === $group_slug ) {
				$extras_output['edge'] = array(
					'name' => (string) ( $edge_override['name'] ?? 'Kanten' ),
					'desc' => (string) ( $edge_override['desc'] ?? 'geschliffen & poliert' ),
				);
				continue;
			}

			$all_options    = $this->extras_catalog->get_options_by_group( $group_slug );
			$allowed_ids    = (array) ( $option_map[ $group_slug ] ?? array() );
			$filtered       = array();
			$allowed_lookup = array_flip( array_map( 'intval', $allowed_ids ) );

			foreach ( $all_options as $option ) {
				$option_id = (int) ( $option['id'] ?? 0 );
				if ( ! empty( $allowed_ids ) && ! in_array( $option_id, $allowed_ids, true ) ) {
					continue;
				}
				$filtered[ $option_id ] = $this->format_shopify_option( $option, $definition );
			}

			if ( ! empty( $allowed_ids ) ) {
				uksort(
					$filtered,
					static function ( int $a, int $b ) use ( $allowed_lookup ): int {
						return ( $allowed_lookup[ $a ] ?? PHP_INT_MAX ) <=> ( $allowed_lookup[ $b ] ?? PHP_INT_MAX );
					}
				);
			}

			$extras_output[ $group_slug ] = array(
				'title' => (string) ( $definition['category_title'] ?? $definition['label'] ?? $group_slug ),
				'value' => array_values( $filtered ),
			);
		}

		$images = array();
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$url = wp_get_attachment_url( $image_id );
			if ( $url ) {
				$images[] = $url;
			}
		}
		foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
			$url = wp_get_attachment_url( $gallery_id );
			if ( $url ) {
				$images[] = $url;
			}
		}

		$data = array(
			'id'               => (string) $product->get_id(),
			'product_id'       => (string) $product->get_id(),
			'title'            => $product->get_name(),
			'vendor'           => '',
			'description'      => $product->get_description(),
			'default_price'    => (string) $product->get_price(),
			'panelTemplate'    => (string) ( $template_data['panel_template'] ?? 'bathroomMirror' ),
			'type'             => (string) ( $template_data['type'] ?? '' ),
			'template'         => (string) ( $template_data['slug'] ?? sanitize_title( $template_data['title'] ?? '' ) ),
			'images'           => $images,
			'min_width'        => (int) ( $dimensions['min_width'] ?? 400 ),
			'max_width'        => (int) ( $dimensions['max_width'] ?? 2500 ),
			'min_height'       => (int) ( $dimensions['min_height'] ?? 400 ),
			'max_height'       => (int) ( $dimensions['max_height'] ?? 2500 ),
			'extras'           => $extras_output,
			'validation_rules' => (array) ( $template_data['validation_rules'] ?? $template_data['rules'] ?? array() ),
			'behavior_rules'   => (array) ( $template_data['behavior_rules'] ?? $template_data['validation_rules'] ?? $template_data['rules'] ?? array() ),
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
	 * Format catalog option for Shopify extras.value[] shape.
	 *
	 * @param array<string, mixed> $option     Option data.
	 * @param array<string, mixed> $group_def  Group definition.
	 * @return array<string, mixed>
	 */
	private function format_shopify_option( array $option, array $group_def ): array {
		$meta        = (array) ( $option['meta'] ?? array() );
		$option_data = isset( $meta['_wcs_option_data'] ) && is_array( $meta['_wcs_option_data'] )
			? $meta['_wcs_option_data']
			: array();

		$legacy_id = (int) ( $meta['_wcs_legacy_id'] ?? 0 );
		if ( $legacy_id <= 0 ) {
			$legacy_id = (int) ( $option['id'] ?? 0 );
		}

		$output = array(
			'id'    => $legacy_id,
			'name'  => (string) ( $option_data['name'] ?? $option['title'] ?? '' ),
			'value' => (string) ( $option_data['value'] ?? $option['slug'] ?? '' ),
			'image' => (string) ( $option_data['image'] ?? $meta['_wcs_image'] ?? '' ),
			'price' => (float) ( $option_data['price'] ?? $meta['_wcs_price'] ?? 0 ),
		);

		$optional_fields = (array) ( $group_def['optional_fields'] ?? array() );
		foreach ( array_keys( $optional_fields ) as $nested_key ) {
			if ( array_key_exists( $nested_key, $option_data ) ) {
				$output[ $nested_key ] = $option_data[ $nested_key ];
			}
		}

		return $output;
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
			$slugs   = $this->selection_to_slugs( $selected );

			foreach ( $slugs as $slug ) {
				foreach ( $options as $option ) {
					if ( ( $option['slug'] ?? '' ) === (string) $slug ) {
						$meta  = (array) ( $option['meta'] ?? array() );
						$data  = (array) ( $meta['_wcs_option_data'] ?? array() );
						$price = (float) ( $data['price'] ?? $meta['_wcs_price'] ?? 0 );
						$total += $price;
					}
				}
			}
		}

		return (float) apply_filters( 'wcs_calculate_configured_price', $total, $base_price, $selections );
	}

	/**
	 * Normalize plain, multi, or enriched selections into option slugs.
	 *
	 * @param mixed $selected Selection.
	 * @return array<int, string>
	 */
	private function selection_to_slugs( $selected ): array {
		if ( ! is_array( $selected ) ) {
			return array( (string) $selected );
		}

		if ( isset( $selected['value'] ) ) {
			return array( (string) $selected['value'] );
		}

		$slugs = array();
		foreach ( $selected as $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['value'] ) ) {
					$slugs[] = (string) $value['value'];
				}
				continue;
			}
			$slugs[] = (string) $value;
		}
		return $slugs;
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
		$template['_option_lookup'] = $this->build_option_lookup( $template );
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

	/**
	 * Build option lookup for backend validation.
	 *
	 * @param array<string, mixed> $template Template data.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function build_option_lookup( array $template ): array {
		$lookup     = array();
		$enabled    = (array) ( $template['enabled_groups'] ?? $template['groups'] ?? array() );
		$option_map = (array) ( $template['extra_option_map'] ?? array() );
		$groups     = $this->extras_registry->get_groups();

		foreach ( $enabled as $group_slug ) {
			$group_slug = (string) $group_slug;
			if ( ! isset( $groups[ $group_slug ] ) ) {
				continue;
			}

			$allowed_ids = (array) ( $option_map[ $group_slug ] ?? array() );
			foreach ( $this->extras_catalog->get_options_by_group( $group_slug ) as $option ) {
				$option_id = (int) ( $option['id'] ?? 0 );
				if ( ! empty( $allowed_ids ) && ! in_array( $option_id, $allowed_ids, true ) ) {
					continue;
				}

				$meta        = (array) ( $option['meta'] ?? array() );
				$option_data = (array) ( $meta['_wcs_option_data'] ?? array() );
				$slug        = (string) ( $option_data['value'] ?? $option['slug'] ?? '' );

				if ( '' === $slug ) {
					continue;
				}

				$lookup[ $group_slug ][ $slug ] = array(
					'id'    => $option_id,
					'value' => $slug,
					'text'  => (string) ( $option_data['name'] ?? $option['title'] ?? '' ),
					'name'  => (string) ( $option_data['name'] ?? $option['title'] ?? '' ),
					'price' => (float) ( $option_data['price'] ?? $meta['_wcs_price'] ?? 0 ),
					'data'  => $option_data,
				);
			}
		}

		return $lookup;
	}
}
