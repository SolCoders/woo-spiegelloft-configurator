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
			$group_slug = sanitize_key( (string) $group_slug );

			if ( ! in_array( $group_slug, $enabled_groups, true ) ) {
				return new WP_Error(
					'wcs_invalid_group',
					sprintf(
						/* translators: %s: group slug */
						__( 'Group "%s" is not enabled for this product.', 'woo-spiegelloft-configurator' ),
						$group_slug
					)
				);
			}

			if ( ! isset( $groups[ $group_slug ] ) ) {
				continue;
			}

			$group_def = $groups[ $group_slug ];
			$result    = $this->validate_group_value( $value, $group_def );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$rules = (array) ( $template['validation_rules'] ?? $template['rules'] ?? array() );
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$result = $this->validate_rule( $selections, $rule );
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
	 * @param array<string, mixed> $rule       Rule definition.
	 * @return true|WP_Error
	 */
	private function validate_rule( array $selections, array $rule ) {
		$when         = (array) ( $rule['when'] ?? array() );
		$field        = (string) ( $rule['when_field'] ?? 'value' );
		$operator     = (string) ( $rule['when_operator'] ?? 'equals' );
		$then         = (string) ( $rule['then'] ?? 'require' );
		$target       = (string) ( $rule['target'] ?? '' );
		$target_value = (string) ( $rule['target_value'] ?? '' );

		$matches = true;
		foreach ( $when as $key => $expected ) {
			$actual = $selections[ $key ] ?? null;
			if ( ! $this->selection_matches( $this->get_comparable_selection_value( $actual, $field ), $expected, $operator ) ) {
				$matches = false;
				break;
			}
		}

		if ( ! $matches ) {
			return true;
		}

		if ( 'require' === $then && $target && empty( $selections[ $target ] ) ) {
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: %s: target group */
					__( 'Required selection missing for %s.', 'woo-spiegelloft-configurator' ),
					$target
				)
			);
		}

		if ( 'disallow' === $then && $target && ! empty( $selections[ $target ] ) ) {
			return new WP_Error(
				'wcs_rule_failed',
				sprintf(
					/* translators: %s: target group */
					__( 'Selection is not allowed for %s.', 'woo-spiegelloft-configurator' ),
					$target
				)
			);
		}

		if ( 'require_value' === $then && $target && $target_value && ! $this->selection_contains( $selections[ $target ] ?? null, $target_value ) ) {
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

		if ( 'disallow_value' === $then && $target && $target_value && $this->selection_contains( $selections[ $target ] ?? null, $target_value ) ) {
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
	 * Check whether a selection matches a rule condition.
	 *
	 * @param mixed  $actual   Actual selection.
	 * @param mixed  $expected Expected selection.
	 * @param string $operator Condition operator.
	 */
	private function selection_matches( $actual, $expected, string $operator ): bool {
		if ( 'selected' === $operator ) {
			return ! empty( $actual );
		}

		if ( 'empty' === $operator ) {
			return empty( $actual );
		}

		if ( in_array( $operator, array( 'greater_than', 'less_than' ), true ) ) {
			if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
				return false;
			}

			return 'greater_than' === $operator
				? (float) $actual > (float) $expected
				: (float) $actual < (float) $expected;
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
	private function get_comparable_selection_value( $selection, string $field ) {
		if ( is_array( $selection ) && isset( $selection[ $field ] ) ) {
			return $selection[ $field ];
		}

		return $selection;
	}

	/**
	 * Check whether a single or multi selection contains a value.
	 *
	 * @param mixed $actual   Actual selection.
	 * @param mixed $expected Expected selection.
	 */
	private function selection_contains( $actual, $expected ): bool {
		if ( is_array( $actual ) ) {
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
			$key = sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_selection_array( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
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
			$key = is_string( $item_key ) ? sanitize_key( $item_key ) : $item_key;
			if ( is_array( $item_value ) ) {
				$sanitized[ $key ] = $this->sanitize_selection_array( $item_value );
				continue;
			}
			$sanitized[ $key ] = sanitize_text_field( (string) $item_value );
		}
		return $sanitized;
	}
}
