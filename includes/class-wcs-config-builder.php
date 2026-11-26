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
			return new WP_Error( 'wcs_invalid_product', __( 'Ungültiges Produkt.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		$template_id = $this->template->get_product_template_id( $product_id );
		if ( $template_id <= 0 ) {
			return new WP_Error( 'wcs_no_template', __( 'Für dieses Produkt wurde keine Konfigurator-Vorlage zugewiesen.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		$template_data = $this->template->get_template_data( $template_id );
		if ( ! $template_data ) {
			return new WP_Error( 'wcs_template_not_found', __( 'Die Konfigurator-Vorlage wurde nicht gefunden.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
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
		$groups         = $this->extras_registry->get_groups_for_template( (int) ( $template_data['id'] ?? 0 ) );
		$enabled        = (array) ( $template_data['enabled_groups'] ?? $template_data['groups'] ?? array() );
		$option_map     = $this->extras_catalog->get_template_option_map( $template_data, $groups );
		$dimensions     = (array) ( $template_data['dimensions'] ?? array() );
		$edge_override  = (array) ( $template_data['edge_override'] ?? array() );
		$extras_output  = array();
		$step_map       = (array) ( $template_data['step_map'] ?? array() );
		$choice_step_map = (array) ( $template_data['choice_step_map'] ?? array() );
		$group_positions = $this->template->get_position_settings( (int) ( $template_data['id'] ?? 0 ) );
		$group_positions = is_array( $group_positions ) ? $group_positions : array();
		$steps_output   = array(
			1 => array(
				'number' => 1,
				'title'  => __( 'Maßauswahl', 'woo-spiegelloft-configurator' ),
				'groups' => array(),
			),
		);

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

			$allowed_ids    = (array) ( $option_map[ $group_slug ] ?? array() );
			$all_options    = empty( $allowed_ids )
				? array()
				: $this->extras_catalog->get_options_for_template_group( $group_slug, $allowed_ids );
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
			if ( ! empty( $group_positions[ $group_slug ]['enabled'] ) && ! empty( $group_positions[ $group_slug ]['options'] ) ) {
				$extras_output[ $group_slug ]['position_config'] = array(
					'label'     => (string) ( $group_positions[ $group_slug ]['label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ),
					'show_when' => (string) ( $group_positions[ $group_slug ]['show_when'] ?? '' ),
					'options'   => array_values( (array) $group_positions[ $group_slug ]['options'] ),
				);
			}

			if ( ! empty( $choice_step_map ) ) {
				foreach ( $filtered as $option_id => $formatted_option ) {
					$choice_step = max( 1, (int) ( $choice_step_map[ (int) $option_id ] ?? $step_map[ $group_slug ] ?? 2 ) );
					$choice_group_slug = $group_slug . '__choice_' . (int) $option_id;
					$extras_output[ $choice_group_slug ] = array(
						'title' => (string) ( $formatted_option['name'] ?? $definition['category_title'] ?? $definition['label'] ?? $group_slug ),
						'value' => array( $formatted_option ),
					);
					if ( ! isset( $steps_output[ $choice_step ] ) ) {
						$steps_output[ $choice_step ] = array(
							'number' => $choice_step,
							'title'  => sprintf(
								/* translators: %d: step number */
								__( 'Schritt %d', 'woo-spiegelloft-configurator' ),
								$choice_step
							),
							'groups' => array(),
						);
					}
					$steps_output[ $choice_step ]['groups'][] = $choice_group_slug;
				}
				unset( $extras_output[ $group_slug ] );
				continue;
			}

			$step_number = max( 1, (int) ( $step_map[ $group_slug ] ?? 2 ) );
			if ( ! isset( $steps_output[ $step_number ] ) ) {
				$steps_output[ $step_number ] = array(
					'number' => $step_number,
					'title'  => sprintf(
						/* translators: %d: step number */
						__( 'Schritt %d', 'woo-spiegelloft-configurator' ),
						$step_number
					),
					'groups' => array(),
				);
			}
			$steps_output[ $step_number ]['groups'][] = $group_slug;
		}

		ksort( $steps_output );

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
			'measurement_mode' => (string) ( $dimensions['measurement_mode'] ?? 'standard' ),
			'top_min_width'    => (int) ( $dimensions['top_min_width'] ?? $dimensions['min_width'] ?? 0 ),
			'top_max_width'    => (int) ( $dimensions['top_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
			'bottom_min_width' => (int) ( $dimensions['bottom_min_width'] ?? $dimensions['min_width'] ?? 0 ),
			'bottom_max_width' => (int) ( $dimensions['bottom_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
			'left_min_height'  => (int) ( $dimensions['left_min_height'] ?? $dimensions['min_height'] ?? 0 ),
			'left_max_height'  => (int) ( $dimensions['left_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
			'right_min_height' => (int) ( $dimensions['right_min_height'] ?? $dimensions['min_height'] ?? 0 ),
			'right_max_height' => (int) ( $dimensions['right_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
			'side_measurements' => array_values( (array) ( $dimensions['side_measurements'] ?? array() ) ),
			'pricing'          => WCS_Pricing::normalize( (array) ( $template_data['pricing'] ?? array() ), (array) ( $dimensions['side_measurements'] ?? array() ) ),
			'extras'           => $extras_output,
			'steps'            => array_values( $steps_output ),
			'step_map'         => $step_map,
			'validation_rules' => (array) ( $template_data['validation_rules'] ?? $template_data['rules'] ?? array() ),
			'behavior_rules'   => (array) ( $template_data['behavior_rules'] ?? $template_data['validation_rules'] ?? $template_data['rules'] ?? array() ),
		);

		/**
		 * Filter built config payload.
		 *
		 * @param array<string, mixed> $data    Config data.
		 * @param WC_Product           $product Product.
		 */
		return WCS_Storefront_Text::config( apply_filters( 'wcs_build_config_payload', $data, $product ) );
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
		// Catalogue lists may predate the last editor save. The saved customer
		// fields (including defaults) are authoritative for the storefront.
		$saved_data = ! empty( $option['id'] ) ? get_post_meta( (int) $option['id'], '_wcs_option_data', true ) : null;
		$option_data = is_array( $saved_data ) ? $saved_data : ( isset( $meta['_wcs_option_data'] ) && is_array( $meta['_wcs_option_data'] )
			? $meta['_wcs_option_data']
			: array() );

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

		if ( ! empty( $option_data['position_enabled'] ) && ! empty( $option_data['position_options'] ) && is_array( $option_data['position_options'] ) ) {
			$output['position_label']   = (string) ( $option_data['position_label'] ?? '' );
			$output['position_options'] = array_values( $option_data['position_options'] );
		}

		$customer_fields = $this->normalize_customer_fields( $option_data );
		if ( ! empty( $customer_fields ) ) {
			$output['customer_fields'] = $customer_fields;
		}

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
	 * @param array<string, mixed> $template   Trusted template settings, when available.
	 * @return float
	 */
	public function calculate_price( float $base_price, array $selections, array $template = array() ): float {
		$total = $base_price;
		$lookup = $template ? $this->build_option_lookup( $template ) : array();

		foreach ( $selections as $group_slug => $selected ) {
			if ( false !== strpos( (string) $group_slug, '.' ) ) {
				continue;
			}

			$slugs   = $this->selection_to_slugs( $selected );
			if ( $template ) {
				// Identical values in other templates must never contribute a price.
				foreach ( array_unique( $slugs ) as $slug ) {
					$option = $lookup[ $group_slug ][ $slug ] ?? null;
					if ( $option ) {
						$total += (float) $option['price'];
						$total += $this->calculate_customer_field_prices( (string) $group_slug, (string) $slug, $option['data'], $selections );
					}
				}
				continue;
			}

			$options = array_merge(
				$this->extras_catalog->get_options_by_group( (string) $group_slug ),
				$this->extras_catalog->get_uncategorized_options()
			);

			foreach ( $slugs as $slug ) {
				foreach ( $options as $option ) {
					$meta        = (array) ( $option['meta'] ?? array() );
					$data        = (array) ( $meta['_wcs_option_data'] ?? array() );
					$option_slug = (string) ( $data['value'] ?? $option['slug'] ?? '' );
					if ( $option_slug === (string) $slug ) {
						$price = (float) ( $data['price'] ?? $meta['_wcs_price'] ?? 0 );
						$total += $price;
						$total += $this->calculate_customer_field_prices( (string) $group_slug, (string) $slug, $data, $selections );
						break;
					}
				}
			}
		}

		if ( $template ) {
			$total += WCS_Pricing::calculate( $template, $selections, $base_price )['total'];
		}
		return round( (float) apply_filters( 'wcs_calculate_configured_price', $total, $base_price, $selections ), wc_get_price_decimals() );
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
			return new WP_Error( 'wcs_no_template', __( 'Keine Konfigurator-Vorlage zugewiesen.', 'woo-spiegelloft-configurator' ) );
		}

		$selections = $this->validation->sanitize_selections( $selections );
		$dimension_valid = WCS_Pricing::validate_selections( $template, $selections );
		if ( is_wp_error( $dimension_valid ) ) {
			return $dimension_valid;
		}
		$template['_option_lookup'] = $this->build_option_lookup( $template );
		$valid      = $this->validation->validate( $selections, $template, $this->extras_registry->get_groups() );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$valid = $this->validate_customer_fields( $selections, $template['_option_lookup'] );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'wcs_invalid_product', __( 'Ungültiges Produkt.', 'woo-spiegelloft-configurator' ) );
		}

		$price = $this->calculate_price( (float) $product->get_price(), $selections, $template );

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
		$choice_step_map = (array) ( $template['choice_step_map'] ?? array() );
		$groups     = $this->extras_registry->get_groups_for_template( (int) ( $template['id'] ?? 0 ) );
		$option_map = $this->extras_catalog->get_template_option_map( $template, $groups );

		foreach ( $enabled as $group_slug ) {
			$group_slug = (string) $group_slug;
			if ( ! isset( $groups[ $group_slug ] ) ) {
				continue;
			}

			$allowed_ids = (array) ( $option_map[ $group_slug ] ?? array() );
			if ( empty( $allowed_ids ) ) {
				continue;
			}
			foreach ( $this->extras_catalog->get_options_for_template_group( $group_slug, $allowed_ids ) as $option ) {
				$option_id = (int) ( $option['id'] ?? 0 );
				if ( ! empty( $allowed_ids ) && ! in_array( $option_id, $allowed_ids, true ) ) {
					continue;
				}

				$meta        = (array) ( $option['meta'] ?? array() );
				$saved_data  = get_post_meta( $option_id, '_wcs_option_data', true );
				$option_data = is_array( $saved_data ) ? $saved_data : (array) ( $meta['_wcs_option_data'] ?? array() );
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
				if ( ! empty( $choice_step_map[ $option_id ] ) ) {
					$lookup[ $group_slug . '__choice_' . $option_id ][ $slug ] = $lookup[ $group_slug ][ $slug ];
				}
			}
		}

		return $lookup;
	}

	/**
	 * Normalize new or legacy per-choice customer fields.
	 *
	 * @param array<string, mixed> $option_data Option data.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_customer_fields( array $option_data ): array {
		if ( ! empty( $option_data['customer_fields'] ) && is_array( $option_data['customer_fields'] ) ) {
			return array_values( array_filter( array_map( array( $this, 'normalize_customer_field' ), $option_data['customer_fields'] ) ) );
		}

		if ( empty( $option_data['position_enabled'] ) || empty( $option_data['position_options'] ) || ! is_array( $option_data['position_options'] ) ) {
			return array();
		}

		return array(
			array(
				'label'         => (string) ( $option_data['position_label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ),
				'key'           => 'position',
				'type'          => 'dropdown',
				'required'      => false,
				'placeholder'   => '',
				'price_enabled' => false,
				'options'       => array_values( $option_data['position_options'] ),
			),
		);
	}

	/**
	 * Normalize one customer field for storefront output.
	 *
	 * @param mixed $field Raw field.
	 * @return array<string, mixed>|null
	 */
	private function normalize_customer_field( $field ): ?array {
		if ( ! is_array( $field ) ) {
			return null;
		}

		$type_raw = (string) ( $field['type'] ?? 'dropdown' );
		$type     = in_array( $type_raw, array( 'dropdown', 'text', 'number' ), true ) ? $type_raw : 'dropdown';
		$row  = array(
			'label'       => (string) ( $field['label'] ?? '' ),
			'key'         => sanitize_title( (string) ( $field['key'] ?? $field['label'] ?? '' ) ),
			'type'        => $type,
			'required'    => ! empty( $field['required'] ),
			'read_only'   => ! empty( $field['read_only'] ),
			'disabled'    => ! empty( $field['disabled'] ),
			'placeholder' => (string) ( $field['placeholder'] ?? '' ),
			'price_enabled' => ! empty( $field['price_enabled'] ),
		);

		if ( '' === $row['label'] || '' === $row['key'] ) {
			return null;
		}

		if ( 'dropdown' === $type ) {
			$row['options']       = array_values( array_filter( array_map( array( $this, 'normalize_customer_field_option' ), (array) ( $field['options'] ?? array() ) ) ) );
			$has_preselected = false;
			foreach ( $row['options'] as &$dropdown_option ) {
				$dropdown_option['preselected'] = ! $has_preselected && $dropdown_option['preselected'];
				$has_preselected = $has_preselected || $dropdown_option['preselected'];
			}
			unset( $dropdown_option );
			if ( empty( $row['options'] ) ) {
				return null;
			}
		} else {
			if ( 'number' === $type ) {
				$row['min'] = (string) ( $field['min'] ?? '' );
				$row['max'] = (string) ( $field['max'] ?? '' );
				$row['step'] = (string) ( $field['step'] ?? '' );
			} elseif ( 'text' === $type ) {
				$row['min'] = (string) ( $field['min'] ?? '' );
				$row['max'] = (string) ( $field['max'] ?? '' );
			}
			if ( ! empty( $row['price_enabled'] ) ) {
				$row['price'] = (float) ( $field['price'] ?? 0 );
			}
		}

		return $row;
	}

	/**
	 * Normalize one customer-field dropdown option.
	 *
	 * @param mixed $option Raw option.
	 * @return array<string, mixed>|null
	 */
	private function normalize_customer_field_option( $option ): ?array {
		if ( ! is_array( $option ) ) {
			return null;
		}

		$value = sanitize_title( (string) ( $option['value'] ?? $option['label'] ?? '' ) );
		$label = (string) ( $option['label'] ?? $value );
		if ( '' === $value || '' === $label ) {
			return null;
		}

		$row = array(
			'label' => $label,
			'value' => $value,
			'preselected' => ! empty( $option['preselected'] ),
			'image' => (string) ( $option['image'] ?? '' ),
			'price' => (float) ( $option['price'] ?? 0 ),
		);

		if ( ! empty( $option['customer_fields'] ) && is_array( $option['customer_fields'] ) ) {
			$row['nested_enabled']  = true;
			$row['customer_fields'] = array_values( array_filter( array_map( array( $this, 'normalize_customer_field' ), $option['customer_fields'] ) ) );
		} elseif ( ! empty( $option['position_enabled'] ) && ! empty( $option['position_options'] ) && is_array( $option['position_options'] ) ) {
			$row['nested_enabled']  = true;
			$row['customer_fields'] = array(
				array(
					'label'         => (string) ( $option['position_label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ),
					'key'           => 'position',
					'type'          => 'dropdown',
					'required'      => true,
					'placeholder'   => '',
					'price_enabled' => false,
					'options'       => array_values( $option['position_options'] ),
				),
			);
		}

		return $row;
	}

	/**
	 * Validate required fields and dropdown values from trusted option metadata.
	 *
	 * @param array<string, mixed> $selections Sanitized selections.
	 * @param array<string, array<string, array<string, mixed>>> $lookup Template options.
	 * @return true|WP_Error
	 */
	private function validate_customer_fields( array $selections, array $lookup ) {
		foreach ( $selections as $group_slug => $selected ) {
			if ( false !== strpos( (string) $group_slug, '.' ) ) {
				continue;
			}

			$option = $lookup[ $group_slug ][ (string) $selected ]['data'] ?? array();
			if ( empty( $option ) ) {
				continue;
			}

			$valid = $this->validate_customer_field_rows( $this->normalize_customer_fields( $option ), (string) $group_slug . '.' . (string) $selected, $selections );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		return true;
	}

	/**
	 * Validate customer field rows recursively.
	 *
	 * @param array<int, array<string, mixed>> $fields     Field rows.
	 * @param string                           $base_path  Submitted key prefix.
	 * @param array<string, mixed>             $selections Sanitized selections.
	 * @return true|WP_Error
	 */
	private function validate_customer_field_rows( array $fields, string $base_path, array $selections ) {
		foreach ( $fields as $field ) {
			$key   = $base_path . '.' . (string) $field['key'];
			$value = $selections[ $key ] ?? '';
			if ( ! empty( $field['required'] ) && ( '' === (string) $value || '---' === (string) $value ) ) {
				return new WP_Error(
					'wcs_required_customer_field',
					sprintf(
						/* translators: %s: customer field label */
						__( 'Bitte füllen Sie das Pflichtfeld „%s“ aus.', 'woo-spiegelloft-configurator' ),
						(string) $field['label']
					)
				);
			}

			if ( 'number' === (string) $field['type'] && '' !== (string) $value ) {
				$number = (float) $value;
				$min    = max( 0.0, (float) ( $field['min'] ?? 0 ) );
				$max    = '' !== (string) ( $field['max'] ?? '' ) ? (float) $field['max'] : null;
				if ( ! is_numeric( $value ) || ( null !== $min && $number < $min ) || ( null !== $max && $number > $max ) ) {
					return new WP_Error(
						'wcs_invalid_customer_field_range',
						sprintf(
							/* translators: %s: customer field label */
							__( 'Ungültiger Wert für „%s“.', 'woo-spiegelloft-configurator' ),
							(string) $field['label']
						)
					);
				}
			}
			if ( 'text' === (string) $field['type'] && '' !== (string) $value ) {
				$word_count = str_word_count( wp_strip_all_tags( (string) $value ) );
				$min_words  = '' !== (string) ( $field['min'] ?? '' ) ? (int) $field['min'] : null;
				$max_words  = '' !== (string) ( $field['max'] ?? '' ) ? (int) $field['max'] : null;
				if ( ( null !== $min_words && $word_count < $min_words ) || ( null !== $max_words && $word_count > $max_words ) ) {
					return new WP_Error(
						'wcs_invalid_customer_field_words',
						sprintf(
							/* translators: %s: field label */
							__( 'Ungültige Wortanzahl für „%s“.', 'woo-spiegelloft-configurator' ),
							(string) ( $field['label'] ?? $key )
						)
					);
				}
			}

			if ( 'dropdown' !== (string) $field['type'] || '' === (string) $value ) {
				continue;
			}

			$field_option = $this->find_customer_field_option( $field, (string) $value );
			if ( ! $field_option ) {
				return new WP_Error(
					'wcs_invalid_customer_field',
					sprintf(
						/* translators: %s: customer field label */
						__( 'Ungültige Auswahl für „%s“.', 'woo-spiegelloft-configurator' ),
						(string) $field['label']
					)
				);
			}

			if ( ! empty( $field_option['customer_fields'] ) && is_array( $field_option['customer_fields'] ) ) {
				$valid = $this->validate_customer_field_rows( (array) $field_option['customer_fields'], $key . '.' . (string) $value, $selections );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
			}
		}

		return true;
	}

	/**
	 * Add trusted dropdown-row prices for selected customer fields.
	 *
	 * @param string               $group_slug Group slug.
	 * @param string               $selected   Selected option slug.
	 * @param array<string, mixed> $option     Option data.
	 * @param array<string, mixed> $selections Sanitized selections.
	 */
	private function calculate_customer_field_prices( string $group_slug, string $selected, array $option, array $selections ): float {
		$total = 0.0;
		return $this->calculate_customer_field_row_prices( $this->normalize_customer_fields( $option ), $group_slug . '.' . $selected, $selections );
	}

	/**
	 * Add trusted dropdown-row prices for customer field rows recursively.
	 *
	 * @param array<int, array<string, mixed>> $fields     Field rows.
	 * @param string                           $base_path  Submitted key prefix.
	 * @param array<string, mixed>             $selections Sanitized selections.
	 */
	private function calculate_customer_field_row_prices( array $fields, string $base_path, array $selections ): float {
		$total = 0.0;
		foreach ( $fields as $field ) {
			$key = $base_path . '.' . (string) $field['key'];
			if ( 'dropdown' !== (string) $field['type'] ) {
				if ( ! empty( $field['price_enabled'] ) && '' !== (string) ( $selections[ $key ] ?? '' ) ) {
					$total += (float) ( $field['price'] ?? 0 );
				}
				continue;
			}

			$value        = (string) ( $selections[ $key ] ?? '' );
			$field_option = '' === $value ? null : $this->find_customer_field_option( $field, $value );
			if ( $field_option && ! empty( $field['price_enabled'] ) ) {
				$total += (float) ( $field_option['price'] ?? 0 );
			}

			if ( $field_option && ! empty( $field_option['customer_fields'] ) && is_array( $field_option['customer_fields'] ) ) {
				$total += $this->calculate_customer_field_row_prices( (array) $field_option['customer_fields'], $key . '.' . $value, $selections );
			}
		}

		return $total;
	}
	/**
	 * Find a dropdown value inside a customer field.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @param string               $value Selected value.
	 * @return array<string, mixed>|null
	 */
	private function find_customer_field_option( array $field, string $value ): ?array {
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( is_array( $option ) && (string) ( $option['value'] ?? '' ) === $value ) {
				return $option;
			}
		}

		return null;
	}

}
