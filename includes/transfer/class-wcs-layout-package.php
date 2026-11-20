<?php
/**
 * Versioned layout package validation and reference mapping.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** Validates portable data before any persistent writes. */
class WCS_Layout_Package {

	public const FORMAT    = 'wcs-mirror-layout';
	public const VERSION   = 2;
	public const MAX_BYTES = 52428800;
	public const MAX_DEPTH = 32;

	/**
	 * Decode and validate an uploaded document.
	 *
	 * @param string $json Json.
	 * @return array|WP_Error
	 */
	public function decode( string $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'wcs_package_size', __( 'The layout JSON exceeds 50 MiB.', 'woo-spiegelloft-configurator' ) );
		}
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory_limit > 0 && memory_get_usage( true ) + strlen( $json ) * 5 > $memory_limit ) {
			return new WP_Error( 'wcs_package_memory', __( 'This layout exceeds the available server memory. Increase the PHP memory limit before importing.', 'woo-spiegelloft-configurator' ) );
		}
		$data = json_decode( $json, true, self::MAX_DEPTH + 2 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'wcs_package_json', __( 'Invalid JSON or excessive nesting.', 'woo-spiegelloft-configurator' ) );
		}
		return $this->validate( $data );
	}

	/**
	 * Validate types, dependencies and references.
	 *
	 * @param array $data Data.
	 * @return array|WP_Error
	 */
	public function validate( array $data ) {
		try {
			$this->require_true( self::FORMAT === ( $data['format'] ?? null ) && in_array( $data['version'] ?? null, array( 1, self::VERSION ), true ), __( 'Unsupported layout format or schema version.', 'woo-spiegelloft-configurator' ) );
			// Older packages may contain media. It is never decoded or imported.
			unset( $data['media'] );
			foreach ( array( 'template', 'options', 'groups', 'positions' ) as $key ) {
				$this->require_true( isset( $data[ $key ] ) && is_array( $data[ $key ] ), __( 'Missing package section: ', 'woo-spiegelloft-configurator' ) . $key );
			}
			$template = $data['template'];
			$this->require_true( is_string( $template['title'] ?? null ) && '' !== trim( $template['title'] ), __( 'The layout title is required.', 'woo-spiegelloft-configurator' ) );
			$this->require_true( in_array( $template['status'] ?? '', array( 'publish', 'draft' ), true ), __( 'Unsupported layout status.', 'woo-spiegelloft-configurator' ) );
			$this->require_true( is_array( $template['data'] ?? null ), __( 'Missing template settings.', 'woo-spiegelloft-configurator' ) );
			$settings = $template['data'];
			if ( isset( $settings['pricing'] ) ) {
				$this->require_true( is_array( $settings['pricing'] ) && 1 === ( $settings['pricing']['version'] ?? null ), __( 'Invalid pricing settings version.', 'woo-spiegelloft-configurator' ) );
				$fields = (array) ( $settings['dimensions']['side_measurements'] ?? array() );
				$this->require_true( ! array_filter( $fields, static fn( $field ) => ! is_array( $field ) ), __( 'Invalid pricing fields.', 'woo-spiegelloft-configurator' ) );
				$keys = array_map( array( 'WCS_Pricing', 'field_key' ), $fields );
				$this->require_true( count( $keys ) === count( array_unique( $keys ) ), __( 'Duplicate pricing field keys.', 'woo-spiegelloft-configurator' ) );
				$normalized = WCS_Pricing::normalize( $settings['pricing'], $fields );
				$this->require_true( is_bool( $settings['pricing']['shipping']['enabled'] ?? null ), __( 'Invalid shipping switch.', 'woo-spiegelloft-configurator' ) );
				// Normalization converts JSON integers to floats; compare numeric values here.
				$this->require_true( $normalized == $settings['pricing'], __( 'Invalid dimension or shipping pricing rules.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Integer and float JSON values are equivalent.
			}
			foreach ( array( 'slug', 'panel_template', 'type' ) as $key ) {
				$this->require_true( is_string( $settings[ $key ] ?? null ), __( 'Invalid layout text setting: ', 'woo-spiegelloft-configurator' ) . $key );
			}
			foreach ( array( 'dimensions', 'enabled_groups', 'group_order', 'choice_order', 'step_map', 'choice_step_map', 'extra_option_map', 'validation_rules', 'behavior_rules', 'edge_override' ) as $key ) {
				$this->require_true( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ), __( 'Invalid layout setting: ', 'woo-spiegelloft-configurator' ) . $key );
			}
			foreach ( $settings['dimensions'] as $key => $dimension ) {
				if ( 'side_measurements' === $key ) {
					$this->require_true( is_array( $dimension ), __( 'Invalid side measurements.', 'woo-spiegelloft-configurator' ) );
					foreach ( $dimension as $side ) {
						$this->require_true( is_array( $side ) && is_string( $side['key'] ?? null ) && is_string( $side['label'] ?? null ) && is_numeric( $side['min'] ?? null ) && is_numeric( $side['max'] ?? null ) && $side['min'] >= 0 && $side['max'] >= $side['min'], __( 'Invalid side measurement range.', 'woo-spiegelloft-configurator' ) );
						$this->require_true( ! isset( $side['step'] ) || ( is_numeric( $side['step'] ) && is_finite( (float) $side['step'] ) && (float) $side['step'] > 0 ), __( 'Invalid side measurement step.', 'woo-spiegelloft-configurator' ) );
					}
				} else {
					$this->require_true( 'measurement_mode' === $key ? in_array( $dimension, array( 'standard', 'sidewise' ), true ) : ( is_numeric( $dimension ) && $dimension >= 0 ), __( 'Invalid dimension setting.', 'woo-spiegelloft-configurator' ) );
				}
			}
			$ids = array();
			foreach ( $data['options'] as $option ) {
				$this->require_true( is_array( $option ) && is_int( $option['id'] ?? null ) && $option['id'] > 0 && ! isset( $ids[ $option['id'] ] ), __( 'Invalid or duplicate customer option ID.', 'woo-spiegelloft-configurator' ) );
				$this->require_true( is_string( $option['title'] ?? null ) && is_string( $option['slug'] ?? null ) && is_string( $option['group'] ?? null ) && is_array( $option['meta'] ?? null ), __( 'Invalid customer option.', 'woo-spiegelloft-configurator' ) );
				foreach ( array_keys( $option['meta'] ) as $key ) {
					$this->require_true( in_array( $key, array( '_wcs_option_data', '_wcs_option_slug', '_wcs_price', '_wcs_legacy_id', '_wcs_image' ), true ), __( 'Unsupported option metadata: ', 'woo-spiegelloft-configurator' ) . $key );
				}
				foreach ( array( '_wcs_option_slug', '_wcs_price', '_wcs_legacy_id', '_wcs_image' ) as $key ) {
					$this->require_true( ! isset( $option['meta'][ $key ] ) || is_scalar( $option['meta'][ $key ] ), __( 'Invalid option metadata value: ', 'woo-spiegelloft-configurator' ) . $key );
				}
				if ( isset( $option['meta']['_wcs_option_data'] ) ) {
					$this->require_true( is_array( $option['meta']['_wcs_option_data'] ), __( 'Invalid option settings.', 'woo-spiegelloft-configurator' ) );
					foreach ( array( 'name', 'value', 'price', 'image', 'required', 'position_label', 'position_enabled' ) as $key ) {
						$this->require_true( ! isset( $option['meta']['_wcs_option_data'][ $key ] ) || is_scalar( $option['meta']['_wcs_option_data'][ $key ] ), __( 'Invalid customer option value.', 'woo-spiegelloft-configurator' ) );
					}
				}
				$ids[ $option['id'] ] = true;
			}
			foreach ( $data['groups'] as $slug => $definition ) {
				$this->require_true( is_string( $slug ) && sanitize_key( $slug ) === $slug && is_array( $definition ) && ( $definition['slug'] ?? null ) === $slug, __( 'Invalid group definition.', 'woo-spiegelloft-configurator' ) );
				$this->require_true( in_array( $definition['type'] ?? 'selectable', array( 'selectable', 'static' ), true ), __( 'Unsupported group type.', 'woo-spiegelloft-configurator' ) );
				$this->validate_field_definitions( $definition['optional_fields'] ?? array() );
			}
			foreach ( $settings['enabled_groups'] as $slug ) {
				$this->require_true( is_string( $slug ) && isset( $data['groups'][ $slug ] ), __( 'Missing group dependency.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $settings['group_order'] as $slug ) {
				$this->require_true( is_string( $slug ) && isset( $data['groups'][ $slug ] ), __( 'Invalid group order dependency.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $settings['step_map'] as $slug => $step ) {
				$this->require_true( isset( $data['groups'][ $slug ] ) && is_int( $step ) && $step > 0, __( 'Invalid group step setting.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $data['positions'] as $slug => $position ) {
				$this->require_true( isset( $data['groups'][ $slug ] ) && is_array( $position ) && is_array( $position['options'] ?? array() ), __( 'Invalid position settings.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $settings['extra_option_map'] as $slug => $selected ) {
				$this->require_true( isset( $data['groups'][ $slug ] ) && is_array( $selected ), __( 'Invalid option group mapping.', 'woo-spiegelloft-configurator' ) );
				foreach ( $selected as $id ) {
					$this->require_true( is_int( $id ) && isset( $ids[ $id ] ), __( 'Missing customer option dependency.', 'woo-spiegelloft-configurator' ) );
				}
			}
			foreach ( $settings['choice_order'] as $id ) {
				$this->require_true( is_int( $id ) && isset( $ids[ $id ] ), __( 'Invalid customer option order reference.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $settings['choice_step_map'] as $id => $step ) {
				$this->require_true( isset( $ids[ $id ] ) && is_int( $step ) && $step > 0, __( 'Invalid customer option step reference.', 'woo-spiegelloft-configurator' ) );
			}
			foreach ( $data['options'] as $option ) {
				$this->require_true( '' === $option['group'] || isset( $data['groups'][ $option['group'] ] ), __( 'Missing option group dependency.', 'woo-spiegelloft-configurator' ) );
			}
			$nodes = 0;
			$this->validate_tree( $data, 0, $nodes, $ids );
			$identity_ids = $ids ? array_combine( array_keys( $ids ), array_keys( $ids ) ) : array();
			foreach ( array( 'validation_rules', 'behavior_rules' ) as $key ) {
				foreach ( $settings[ $key ] as $rule ) {
					$this->require_true( is_array( $rule ), __( 'Invalid restriction rule.', 'woo-spiegelloft-configurator' ) );
					foreach ( array( 'conditions', 'actions' ) as $rows ) {
						if ( isset( $rule[ $rows ] ) ) {
							$this->require_true( is_array( $rule[ $rows ] ), __( 'Invalid restriction rows.', 'woo-spiegelloft-configurator' ) );
							foreach ( $rule[ $rows ] as $row ) {
								$this->require_true( is_array( $row ) && count( array_filter( $row, 'is_array' ) ) === 0, __( 'Invalid restriction row values.', 'woo-spiegelloft-configurator' ) );
							}
						}
					}
				}
				$this->remap_rules( $settings[ $key ], $identity_ids );
			}
			return $this->without_images( $data );
		} catch ( Throwable $error ) {
			return new WP_Error( 'wcs_invalid_package', $error->getMessage() );
		}
	}

	/**
	 * Blank image values while retaining field definitions for manual uploads.
	 *
	 * @param array $data Package data.
	 * @return array
	 */
	public function without_images( array $data ): array {
		unset( $data['media'] );
		$image_keys = array(
			'image'      => true,
			'_wcs_image' => true,
		);
		$discover   = function ( array $definitions ) use ( &$discover, &$image_keys ): void {
			foreach ( $definitions as $key => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( 'image' === ( $field['type'] ?? '' ) ) {
					$field_id = (string) ( $field['id'] ?? ( is_string( $key ) ? $key : '' ) );
					if ( '' !== $field_id ) {
						$image_keys[ $field_id ] = true;
					}
				}
				$discover( $field );
			}
		};
		$discover( (array) ( $data['groups'] ?? array() ) );
		$clear = function ( array $values ) use ( &$clear, $image_keys ): array {
			foreach ( $values as $key => $value ) {
				if ( is_array( $value ) ) {
					$values[ $key ] = $clear( $value );
				} elseif ( isset( $image_keys[ $key ] ) || ( 'default' === $key && 'image' === ( $values['type'] ?? '' ) ) ) {
					$values[ $key ] = '';
				}
			}
			return $values;
		};
		return $clear( $data );
	}

	/**
	 * Reject unsupported types and bound recursive work.
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Depth.
	 * @param int   $nodes Nodes.
	 * @param array $ids Ids.
	 */
	private function validate_tree( $value, int $depth, int &$nodes, array $ids ): void {
		$this->require_true( $depth <= self::MAX_DEPTH && ++$nodes <= 100000, __( 'Layout nesting or item limit exceeded.', 'woo-spiegelloft-configurator' ) );
		if ( ! is_array( $value ) ) {
			$this->require_true( is_scalar( $value ) || null === $value, __( 'Unsupported package value.', 'woo-spiegelloft-configurator' ) );
			return;
		}
		foreach ( $value as $key => $child ) {
			if ( 'customer_fields' === $key ) {
				$this->require_true( is_array( $child ), __( 'Invalid nested customer fields.', 'woo-spiegelloft-configurator' ) );
				foreach ( $child as $field ) {
					$this->require_true( is_array( $field ) && is_string( $field['key'] ?? null ) && '' !== $field['key'] && is_string( $field['label'] ?? null ) && in_array( $field['type'] ?? '', array( 'dropdown', 'text', 'number' ), true ), __( 'Invalid customer field definition.', 'woo-spiegelloft-configurator' ) );
					foreach ( array( 'price', 'min', 'max', 'step', 'placeholder', 'required', 'read_only', 'disabled' ) as $property ) {
						$this->require_true( ! isset( $field[ $property ] ) || is_scalar( $field[ $property ] ), __( 'Invalid customer field setting.', 'woo-spiegelloft-configurator' ) );
					}
					if ( 'dropdown' === $field['type'] ) {
						$this->require_true( is_array( $field['options'] ?? null ), __( 'Missing dropdown options.', 'woo-spiegelloft-configurator' ) );
						foreach ( $field['options'] as $option ) {
							$this->require_true( is_array( $option ) && is_string( $option['label'] ?? null ) && is_string( $option['value'] ?? null ), __( 'Invalid dropdown option.', 'woo-spiegelloft-configurator' ) );
						}
					}
				}
			}
			if ( in_array( $key, array( 'path', 'target', 'target_value', 'when_path' ), true ) && is_string( $child ) ) {
				preg_match_all( '/__choice_(\d+)(?=\.|$)/', $child, $referencees );
				foreach ( $referencees[1] as $id ) {
					$this->require_true( isset( $ids[ (int) $id ] ), __( 'Unresolved customer option rule reference.', 'woo-spiegelloft-configurator' ) );
				}
			}
			$this->validate_tree( $child, $depth + 1, $nodes, $ids );
		}
	}

	/**
	 * Validate legacy field schemas against the types shipped by this plugin.
	 *
	 * @param mixed $fields Fields.
	 */
	private function validate_field_definitions( $fields ): void {
		$this->require_true( is_array( $fields ), __( 'Invalid legacy field definitions.', 'woo-spiegelloft-configurator' ) );
		foreach ( $fields as $field ) {
			$this->require_true( is_array( $field ) && in_array( $field['type'] ?? '', array( 'text', 'price', 'image', 'boolean', 'repeater', 'nested_options' ), true ), __( 'Unsupported legacy field type.', 'woo-spiegelloft-configurator' ) );
			if ( isset( $field['fields'] ) ) {
				$this->validate_field_definitions( $field['fields'] );
			}
		}
	}

	/**
	 * Assert a package invariant.
	 *
	 * @param bool   $valid Valid.
	 * @param string $message Message.
	 * @throws InvalidArgumentException When a package invariant fails.
	 */
	private function require_true( bool $valid, string $message ): void {
		if ( ! $valid ) {
			throw new InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
		}
	}

	/**
	 * Sanitize JSON scalars recursively without interpreting serialized PHP.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_tree( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$value[ $key ] = $this->sanitize_tree( $child );
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			return preg_match( '#^https?://#i', $value ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
		}
		return $value;
	}

	/**
	 * Remap structured rule paths, legacy map keys and formula placeholders.
	 *
	 * @param array $rules Rules.
	 * @param array $ids Ids.
	 * @throws InvalidArgumentException When a package invariant fails.
	 */
	public function remap_rules( array $rules, array $ids ): array {
		$replace = static function ( string $text ) use ( $ids ): string {
			return (string) preg_replace_callback(
				'/__choice_(\d+)(?=[.}\s]|$)/',
				static function ( array $reference ) use ( $ids ): string {
					if ( ! isset( $ids[ (int) $reference[1] ] ) ) {
						throw new InvalidArgumentException( __( 'Unresolved customer option rule reference.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
					}
					return '__choice_' . $ids[ (int) $reference[1] ];
				},
				$text
			);
		};
		$result  = array();
		foreach ( $rules as $key => $value ) {
			$mapped_key = is_string( $key ) ? $replace( $key ) : $key;
			if ( is_array( $value ) ) {
				$value = $this->remap_rules( $value, $ids );
			} elseif ( is_string( $value ) && ( in_array( $key, array( 'path', 'target', 'target_value', 'when_path', 'when_group', 'value', 'min', 'max' ), true ) || false !== strpos( $value, '{' ) ) ) {
				$value = $replace( $value );
			}
			$result[ $mapped_key ] = $value;
		}
		return $result;
	}
}
