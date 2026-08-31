<?php
/**
 * Configuration validation engine.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Validation_Engine
 */
class WCS_Validation_Engine {

	/**
	 * Validate configuration selections against template and groups.
	 *
	 * @param array<string, mixed> $selections User selections.
	 * @param array<string, mixed> $template   Template data.
	 * @param array<string, array<string, mixed>> $groups Group definitions.
	 * @return true|WP_Error
	 */
	public function validate( array $selections, array $template, array $groups ) {
		$enabled_groups = (array) ( $template['enabled_groups'] ?? $template['groups'] ?? array() );

		foreach ( $selections as $group_slug => $value ) {
			$group_slug = $this->sanitize_selection_key( (string) $group_slug );
			$root_slug  = strtok( $group_slug, '.' ) ?: $group_slug;
			$enabled_root_slug = preg_replace( '/__choice_\d+$/', '', $root_slug );

			if ( ! in_array( $root_slug, $enabled_groups, true ) && ! in_array( $enabled_root_slug, $enabled_groups, true ) && ! $this->is_dimension_key( $group_slug ) ) {
				return new WP_Error(
					'wcs_invalid_group',
					sprintf(
						/* translators: %s: group slug */
						__( 'Group "%s" is not enabled for this product.', 'woo-spiegelloft-configurator' ),
						$group_slug
					)
				);
			}

			if ( $root_slug !== $group_slug ) {
				continue;
			}

			if ( ! isset( $groups[ $group_slug ] ) ) {
				continue;
			}

			$group_def = $groups[ $group_slug ];
			$result    = $this->validate_group_value( $value, $group_def );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$result = $this->validate_known_selection( $group_slug, $value, (array) ( $template['_option_lookup'] ?? array() ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$rules = (array) ( $template['validation_rules'] ?? $template['rules'] ?? array() );
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$result = $this->validate_rule( $selections, $template, $rule );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		/**
		 * Filter validation result.
		 *
		 * @param true|WP_Error        $result     Validation result.
		 * @param array<string, mixed> $selections Selections.
		 * @param array<string, mixed> $template   Template.
		 */
		$filtered = apply_filters( 'wcs_validate_configuration', true, $selections, $template );
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}

		return true;
	}

	/**
	 * Validate a single group value.
	 *
	 * @param mixed                $value     Selection value.
	 * @param array<string, mixed> $group_def Group definition.
	 * @return true|WP_Error
	 */
	private function validate_group_value( $value, array $group_def ) {
		$type = (string) ( $group_def['input_type'] ?? 'single' );

		if ( 'none' === $value || null === $value || '' === $value ) {
			if ( ! empty( $group_def['required'] ) ) {
				return new WP_Error(
					'wcs_required_group',
					sprintf(
						/* translators: %s: group label */
						__( 'Selection required for %s.', 'woo-spiegelloft-configurator' ),
						(string) ( $group_def['label'] ?? $group_def['slug'] ?? '' )
					)
				);
			}
			return true;
		}

		if ( 'multi' === $type && ! is_array( $value ) ) {
			return new WP_Error( 'wcs_invalid_multi', __( 'Invalid multi-select value.', 'woo-spiegelloft-configurator' ) );
		}

		return true;
	}

	/**
	 * Validate a conditional rule.
	 *
	 * @param array<string, mixed> $selections Selections.
	 * @param array<string, mixed> $template   Template data.
	 * @param array<string, mixed> $rule       Rule definition.
	 * @return true|WP_Error
	 */
	private function validate_rule( array $selections, array $template, array $rule ) {
		$when          = (array) ( $rule['when'] ?? array() );
		$option_lookup = (array) ( $template['_option_lookup'] ?? array() );
		$conditions    = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? (array) $rule['conditions'] : array();
		$actions       = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? (array) $rule['actions'] : array();

		if ( empty( $conditions ) ) {
			foreach ( $when as $key => $expected ) {
				$conditions[] = array(
					'source'   => (string) ( $rule['when_source'] ?? 'category' ),
					'path'     => (string) $key,
					'field'    => (string) ( $rule['when_field'] ?? 'value' ),
					'type'     => 'dimension' === (string) ( $rule['when_source'] ?? '' ) ? 'number' : 'selection',
					'operator' => (string) ( $rule['when_operator'] ?? 'equals' ),
					'value'    => $expected,
				);
			}
		}

		$results = array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}
			$key      = (string) ( $condition['path'] ?? '' );
			$source   = (string) ( $condition['source'] ?? 'category' );
			$field    = (string) ( $condition['field'] ?? 'value' );
			$operator = (string) ( $condition['operator'] ?? 'equals' );
			$expected = (string) ( $condition['value'] ?? '' );
			$actual   = $this->get_selection_path_value( $selections, $key );
			if ( 'dimension' === $source && null === $actual ) {
				$actual = $this->evaluate_formula( $key, $this->build_formula_context( $selections, $template ) );
			}
			$results[] = $this->selection_matches( $this->get_comparable_selection_value( $actual, $field, $key, $option_lookup ), $expected, $operator );
		}

		$matches = 'any' === (string) ( $rule['match'] ?? 'all' ) ? in_array( true, $results, true ) : ! in_array( false, $results, true );

		if ( ! $matches ) {
			return true;
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'action'       => (string) ( $rule['then'] ?? 'require' ),
				'target_type'  => (string) ( $rule['target_type'] ?? 'category' ),
				'target'       => (string) ( $rule['target'] ?? '' ),
				'target_value' => (string) ( $rule['target_value'] ?? '' ),
			);
		}

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$then         = (string) ( $action['action'] ?? $action['then'] ?? 'require' );
			$target       = (string) ( $action['target'] ?? '' );
			$target_value = (string) ( $action['target_value'] ?? '' );
			$target_type  = (string) ( $action['target_type'] ?? 'category' );

			$result = $this->validate_matched_action( $selections, $template, $then, $target, $target_value, $target_type, $rule );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	private function validate_matched_action( array $selections, array $template, string $then, string $target, string $target_value, string $target_type, array $rule ) {
		$option_lookup = (array) ( $template['_option_lookup'] ?? array() );

		if ( in_array( $then, array( 'show', 'hide', 'clear', 'disable_option', 'disable', 'enable', 'set_min', 'set_max' ), true ) ) {
			return true;
		}

		if ( 'validate_range' === $then ) {
			return $this->validate_range_rule( $selections, $template, $rule );
		}

		$target_actual = $this->get_rule_target_value( $selections, $target, $target_value, $target_type );

		if ( 'require' === $then && $target && $this->is_empty_selection( $target_actual ) ) {
			if ( $this->is_superseded_by_customer_field( $selections, $target, $option_lookup ) ) {
				return true;
			}
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: %s: target group */
					__( 'Required selection missing for %s.', 'woo-spiegelloft-configurator' ),
					$target
				)
			);
		}

		if ( 'disallow' === $then && $target && ! $this->is_empty_selection( $target_actual ) ) {
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: %s: target group */
					__( 'Selection is not allowed for %s.', 'woo-spiegelloft-configurator' ),
					$target
				)
			);
		}

		if ( 'require_value' === $then && $target && $target_value && ! $this->selection_contains( $target_actual, $target_value ) ) {
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: 1: target value, 2: target group */
					__( 'Selection "%1$s" is required for %2$s.', 'woo-spiegelloft-configurator' ),
					$target_value,
					$target
				)
			);
		}

		if ( 'disallow_value' === $then && $target && $target_value && $this->selection_contains( $target_actual, $target_value ) ) {
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: 1: target value, 2: target group */
					__( 'Selection "%1$s" is not allowed for %2$s.', 'woo-spiegelloft-configurator' ),
					$target_value,
					$target
				)
			);
		}

		return true;
	}

	/**
	 * Validate that submitted option slugs still exist for enabled template options.
	 *
	 * @param string                                    $group_slug    Group slug.
	 * @param mixed                                     $value         Submitted value.
	 * @param array<string, array<string, array<mixed>>> $option_lookup Option lookup.
	 * @return true|WP_Error
	 */
	private function validate_known_selection( string $group_slug, $value, array $option_lookup ) {
		if ( ! isset( $option_lookup[ $group_slug ] ) || $this->is_empty_selection( $value ) ) {
			return true;
		}

		foreach ( $this->selection_to_values( $value ) as $selected ) {
			if ( $this->is_empty_selection( $selected ) ) {
				continue;
			}

			if ( ! isset( $option_lookup[ $group_slug ][ (string) $selected ] ) ) {
				return new WP_Error(
					'wcs_invalid_selection',
					sprintf(
						/* translators: 1: selected value, 2: group slug */
						__( 'Selection "%1$s" is not available for %2$s.', 'woo-spiegelloft-configurator' ),
						(string) $selected,
						$group_slug
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate a formula-based numeric range rule.
	 *
	 * @param array<string, mixed> $selections Selections.
	 * @param array<string, mixed> $template   Template data.
	 * @param array<string, mixed> $rule       Rule.
	 * @return true|WP_Error
	 */
	private function validate_range_rule( array $selections, array $template, array $rule ) {
		$target_path = (string) ( $rule['target_value'] ?? $rule['target'] ?? '' );
		if ( '' === $target_path && ! empty( $rule['target'] ) ) {
			$target_path = (string) $rule['target'];
		}

		$actual = $this->get_selection_path_value( $selections, $target_path );
		if ( $this->is_empty_selection( $actual ) || ! is_numeric( $actual ) ) {
			if ( $this->is_superseded_by_customer_field( $selections, $target_path, (array) ( $template['_option_lookup'] ?? array() ) ) ) {
				return true;
			}
			return $this->range_error( $target_path, $rule, __( 'Invalid numeric value for %s.', 'woo-spiegelloft-configurator' ) );
		}

		$context = $this->build_formula_context( $selections, $template );
		$min     = $this->evaluate_formula( (string) ( $rule['min'] ?? '' ), $context );
		$max     = $this->evaluate_formula( (string) ( $rule['max'] ?? '' ), $context );
		$value   = (float) $actual;

		if ( null !== $min && $value < $min ) {
			return $this->range_error( $target_path, $rule, __( 'Value for %s is below the allowed minimum.', 'woo-spiegelloft-configurator' ) );
		}

		if ( null !== $max && $value > $max ) {
			return $this->range_error( $target_path, $rule, __( 'Value for %s is above the allowed maximum.', 'woo-spiegelloft-configurator' ) );
		}

		return true;
	}

	/**
	 * Build a range validation error.
	 *
	 * @param string               $target_path Target path.
	 * @param array<string, mixed> $rule        Rule.
	 * @param string               $fallback    Fallback message.
	 */
	private function range_error( string $target_path, array $rule, string $fallback ): WP_Error {
		$message = (string) ( $rule['message'] ?? '' );
		if ( '' === $message ) {
			$message = sprintf( $fallback, $target_path );
		}

		return new WP_Error( 'wcs_rule_failed', $message );
	}

	/**
	 * Check whether a selection matches a rule condition.
	 *
	 * @param mixed  $actual   Actual selection.
	 * @param mixed  $expected Expected selection.
	 * @param string $operator Condition operator.
	 */
	private function selection_matches( $actual, $expected, string $operator ): bool {
		if ( in_array( $operator, array( 'selected', 'is_not_empty' ), true ) ) {
			return ! empty( $actual );
		}

		if ( in_array( $operator, array( 'empty', 'is_empty' ), true ) ) {
			return empty( $actual );
		}

		if ( 'is_true' === $operator ) {
			return in_array( strtolower( (string) $actual ), array( '1', 'true', 'yes', 'on' ), true );
		}

		if ( 'is_false' === $operator ) {
			return empty( $actual ) || in_array( strtolower( (string) $actual ), array( '0', 'false', 'no', 'off' ), true );
		}

		if ( in_array( $operator, array( 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal' ), true ) ) {
			if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
				return false;
			}

			if ( 'greater_than' === $operator ) {
				return (float) $actual > (float) $expected;
			}
			if ( 'greater_than_or_equal' === $operator ) {
				return (float) $actual >= (float) $expected;
			}
			return 'less_than' === $operator ? (float) $actual < (float) $expected : (float) $actual <= (float) $expected;
		}

		if ( in_array( $operator, array( 'contains', 'not_contains' ), true ) ) {
			$contains = false !== stripos( (string) $actual, (string) $expected );
			return 'not_contains' === $operator ? ! $contains : $contains;
		}

		if ( in_array( $operator, array( 'one_of', 'not_one_of' ), true ) ) {
			$values = array_map( 'trim', explode( ',', (string) $expected ) );
			$hit    = in_array( (string) $actual, $values, true );
			return 'not_one_of' === $operator ? ! $hit : $hit;
		}

		$contains = $this->selection_contains( $actual, $expected );
		return 'not_equals' === $operator ? ! $contains : $contains;
	}

	/**
	 * Extract a comparable value from a submitted selection.
	 *
	 * @param mixed  $selection Submitted selection.
	 * @param string $field     Rule comparison field.
	 * @return mixed
	 */
	private function get_comparable_selection_value( $selection, string $field, string $group_path = '', array $option_lookup = array() ) {
		if ( is_array( $selection ) && isset( $selection[ $field ] ) ) {
			return $selection[ $field ];
		}

		if ( in_array( $field, array( 'price', 'text', 'name' ), true ) ) {
			$group_slug = strtok( $group_path, '.' );
			if ( $group_slug && ! is_array( $selection ) && isset( $option_lookup[ $group_slug ][ (string) $selection ] ) ) {
				return $option_lookup[ $group_slug ][ (string) $selection ][ $field ] ?? $option_lookup[ $group_slug ][ (string) $selection ]['name'] ?? $selection;
			}
		}

		return $selection;
	}

	/**
	 * Resolve target value according to target type/path.
	 *
	 * @param array<string, mixed> $selections   Selections.
	 * @param string               $target       Target group/path.
	 * @param string               $target_value Target value/path.
	 * @param string               $target_type  Target type.
	 * @return mixed
	 */
	private function get_rule_target_value( array $selections, string $target, string $target_value, string $target_type ) {
		if ( in_array( $target_type, array( 'nested', 'dimension' ), true ) && '' !== $target_value ) {
			return $this->get_selection_path_value( $selections, $target_value );
		}

		return $this->get_selection_path_value( $selections, $target );
	}

	/**
	 * Resolve dot-path values from selection state.
	 *
	 * @param array<string, mixed> $selections Selections.
	 * @param string               $path       Dot path.
	 * @return mixed
	 */
	private function get_selection_path_value( array $selections, string $path ) {
		if ( '' === $path ) {
			return null;
		}

		if ( array_key_exists( $path, $selections ) ) {
			return $selections[ $path ];
		}

		$value = $selections;
		foreach ( explode( '.', $path ) as $part ) {
			if ( is_array( $value ) && array_key_exists( $part, $value ) ) {
				$value = $value[ $part ];
				continue;
			}
			return null;
		}

		return $value;
	}

	/**
	 * Build formula context from submitted selections and template dimensions.
	 *
	 * @param array<string, mixed> $selections Selections.
	 * @param array<string, mixed> $template   Template data.
	 * @return array<string, float>
	 */
	private function build_formula_context( array $selections, array $template ): array {
		$dimensions = (array) ( $template['dimensions'] ?? array() );
		$context    = array(
			'min_width'        => (float) ( $dimensions['min_width'] ?? 0 ),
			'max_width'        => (float) ( $dimensions['max_width'] ?? 0 ),
			'min_height'       => (float) ( $dimensions['min_height'] ?? 0 ),
			'max_height'       => (float) ( $dimensions['max_height'] ?? 0 ),
			'top_min_width'    => (float) ( $dimensions['top_min_width'] ?? 0 ),
			'top_max_width'    => (float) ( $dimensions['top_max_width'] ?? 2000 ),
			'bottom_min_width' => (float) ( $dimensions['bottom_min_width'] ?? 0 ),
			'bottom_max_width' => (float) ( $dimensions['bottom_max_width'] ?? 2000 ),
			'left_min_height'  => (float) ( $dimensions['left_min_height'] ?? 0 ),
			'left_max_height'  => (float) ( $dimensions['left_max_height'] ?? 2000 ),
			'right_min_height' => (float) ( $dimensions['right_min_height'] ?? 0 ),
			'right_max_height' => (float) ( $dimensions['right_max_height'] ?? 2000 ),
		);

		foreach ( $selections as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$context[ sanitize_key( (string) $key ) ] = (float) $value;
			}
		}

		return $context;
	}

	/**
	 * Evaluate a simple formula expression.
	 *
	 * @param string               $formula Formula.
	 * @param array<string, float> $context Values.
	 * @return float|null
	 */
	private function evaluate_formula( string $formula, array $context ): ?float {
		$formula = trim( $formula );
		if ( '' === $formula ) {
			return null;
		}

		$formula = preg_replace_callback(
			'/\{([^}]+)\}/',
			static function ( array $matches ) use ( $context ): string {
				$key = sanitize_key( trim( (string) $matches[1] ) );
				return array_key_exists( $key, $context ) ? (string) $context[ $key ] : '0';
			},
			$formula
		) ?? $formula;

		if ( is_numeric( $formula ) ) {
			return (float) $formula;
		}

		if ( preg_match( '/^floor\((.+)\)$/', $formula, $matches ) ) {
			$value = $this->evaluate_formula( $matches[1], $context );
			return null === $value ? null : floor( $value );
		}

		if ( preg_match( '/^(max|min)\(([^,]+),([^)]+)\)(?:\s*([+-])\s*(.+))?$/', $formula, $matches ) ) {
			$left  = $this->evaluate_formula( $matches[2], $context );
			$right = $this->evaluate_formula( $matches[3], $context );
			if ( null === $left || null === $right ) {
				return null;
			}
			$value = 'max' === $matches[1] ? max( $left, $right ) : min( $left, $right );
			if ( ! empty( $matches[4] ) ) {
				$delta = $this->evaluate_formula( $matches[5], $context );
				if ( null === $delta ) {
					return null;
				}
				$value = '+' === $matches[4] ? $value + $delta : $value - $delta;
			}
			return $value;
		}

		if ( preg_match( '/^count_gte\(([^,]+),([^,]+),([^,]+),([^,]+),([^)]+)\)$/', $formula, $matches ) ) {
			$threshold = $this->evaluate_formula( $matches[5], $context );
			if ( null === $threshold ) {
				return null;
			}

			$count = 0;
			for ( $i = 1; $i <= 4; $i++ ) {
				$value = $this->evaluate_formula( $matches[ $i ], $context );
				if ( null !== $value && $value >= $threshold ) {
					$count++;
				}
			}
			return (float) $count;
		}

		foreach ( array( '+', '-' ) as $operator ) {
			$parts = $this->split_formula( $formula, $operator );
			if ( $parts ) {
				$left  = $this->evaluate_formula( $parts[0], $context );
				$right = $this->evaluate_formula( $parts[1], $context );
				if ( null === $left || null === $right ) {
					return null;
				}
				return '+' === $operator ? $left + $right : $left - $right;
			}
		}

		foreach ( array( '*', '/' ) as $operator ) {
			$parts = $this->split_formula( $formula, $operator );
			if ( $parts ) {
				$left  = $this->evaluate_formula( $parts[0], $context );
				$right = $this->evaluate_formula( $parts[1], $context );
				if ( null === $left || null === $right || ( '/' === $operator && 0.0 === $right ) ) {
					return null;
				}
				return '*' === $operator ? $left * $right : $left / $right;
			}
		}

		$key = sanitize_key( $formula );
		return array_key_exists( $key, $context ) ? (float) $context[ $key ] : null;
	}

	/**
	 * Split a formula at a top-level operator.
	 *
	 * @param string $formula  Formula.
	 * @param string $operator Operator.
	 * @return array<int, string>|null
	 */
	private function split_formula( string $formula, string $operator ): ?array {
		$depth = 0;
		for ( $i = strlen( $formula ) - 1; $i >= 0; $i-- ) {
			$char = $formula[ $i ];
			if ( ')' === $char ) {
				$depth++;
			} elseif ( '(' === $char ) {
				$depth--;
			} elseif ( 0 === $depth && $operator === $char && 0 !== $i ) {
				return array( trim( substr( $formula, 0, $i ) ), trim( substr( $formula, $i + 1 ) ) );
			}
		}

		return null;
	}

	/**
	 * Check whether a selection is empty or a placeholder.
	 *
	 * @param mixed $value Selection value.
	 */
	private function is_empty_selection( $value ): bool {
		return null === $value || '' === $value || 'none' === $value || '---' === $value || array() === $value;
	}

	/**
	 * Determine if a submitted key is a supported dimension field.
	 *
	 * @param string $key Selection key.
	 */
	private function is_dimension_key( string $key ): bool {
		if ( 0 === strpos( $key, 'side_' ) ) {
			return true;
		}
		return in_array(
			$key,
			array(
				'width',
				'height',
				'diameter',
				'top_width',
				'bottom_width',
				'left_height',
				'right_height',
			),
			true
		);
	}

	/**
	 * Determine whether a legacy group.field rule is handled by a selected option field.
	 *
	 * @param array<string, mixed> $selections    Selections.
	 * @param string               $target_path   Legacy target path.
	 * @param array<string, mixed> $option_lookup Option lookup.
	 */
	private function is_superseded_by_customer_field( array $selections, string $target_path, array $option_lookup ): bool {
		$parts = explode( '.', $target_path );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$group_slug = $parts[0];
		$field_key  = $parts[1];
		$selected   = (string) ( $selections[ $group_slug ] ?? '' );
		if ( '' === $selected || empty( $option_lookup[ $group_slug ][ $selected ]['data']['customer_fields'] ) ) {
			return false;
		}

		foreach ( (array) $option_lookup[ $group_slug ][ $selected ]['data']['customer_fields'] as $field ) {
			if ( is_array( $field ) && sanitize_key( (string) ( $field['key'] ?? '' ) ) === sanitize_key( $field_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert a selection into comparable option values.
	 *
	 * @param mixed $value Selection.
	 * @return array<int, mixed>
	 */
	private function selection_to_values( $value ): array {
		if ( ! is_array( $value ) ) {
			return array( $value );
		}

		if ( isset( $value['value'] ) ) {
			return array( $value['value'] );
		}

		return array_values(
			array_filter(
				$value,
				static function ( $item ): bool {
					return ! is_array( $item );
				}
			)
		);
	}

	/**
	 * Check whether a single or multi selection contains a value.
	 *
	 * @param mixed $actual   Actual selection.
	 * @param mixed $expected Expected selection.
	 */
	private function selection_contains( $actual, $expected ): bool {
		if ( is_array( $actual ) ) {
			if ( isset( $actual['value'] ) ) {
				return (string) $actual['value'] === (string) $expected;
			}
			return in_array( (string) $expected, array_map( 'strval', $actual ), true );
		}

		if ( is_array( $expected ) ) {
			return in_array( (string) $actual, array_map( 'strval', $expected ), true );
		}

		return (string) $actual === (string) $expected;
	}

	/**
	 * Sanitize configuration selections.
	 *
	 * @param array<string, mixed> $selections Raw selections.
	 * @return array<string, mixed>
	 */
	public function sanitize_selections( array $selections ): array {
		$sanitized = array();
		foreach ( $selections as $key => $value ) {
			$key = $this->sanitize_selection_key( (string) $key );
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_selection_array( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
	}

	/**
	 * Sanitize a selection key while preserving rule dot paths.
	 *
	 * @param string $key Raw key.
	 */
	private function sanitize_selection_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_.-]/', '', strtolower( $key ) ) ?? '';
	}

	/**
	 * Sanitize a nested selection array.
	 *
	 * @param array<mixed> $value Raw selection array.
	 * @return array<mixed>
	 */
	private function sanitize_selection_array( array $value ): array {
		$sanitized = array();
		foreach ( $value as $item_key => $item_value ) {
			$key = is_string( $item_key ) ? $this->sanitize_selection_key( $item_key ) : $item_key;
			if ( is_array( $item_value ) ) {
				$sanitized[ $key ] = $this->sanitize_selection_array( $item_value );
				continue;
			}
			$sanitized[ $key ] = sanitize_text_field( (string) $item_value );
		}
		return $sanitized;
	}
}
