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
		$when   = (array) ( $rule['when'] ?? array() );
		$then   = (string) ( $rule['then'] ?? 'require' );
		$target = (string) ( $rule['target'] ?? '' );

		$matches = true;
		foreach ( $when as $key => $expected ) {
			$actual = $selections[ $key ] ?? null;
			if ( is_array( $expected ) ) {
				if ( ! in_array( $actual, $expected, true ) ) {
					$matches = false;
					break;
				}
			} elseif ( (string) $actual !== (string) $expected ) {
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

		return true;
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
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
	}
}