<?php
/**
 * Dimension and shipping pricing.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

/** Evaluates trusted template pricing without accepting customer-supplied amounts. */
class WCS_Pricing {

	/**
	 * Preserve cents in configured amounts and WooCommerce totals.
	 *
	 * @param mixed $decimals WooCommerce decimal setting.
	 */
	public static function price_decimals( $decimals ): int {
		return max( 2, absint( $decimals ) );
	}

	/** Complete cumulative surcharge table, in ascending threshold order. */
	public static function default_rules(): array {
		$amounts = array( 26, 3, 3.5, 3.5, 3.5, 3.5, 4, 4.5, 4, 4.5, 4.5, 5, 5.5, 5, 5, 5.5, 5.5, 6, 6, 6, 6.5, 6, 6.5, 7, 27.5, 7.5, 7.5, 7.5, 8, 8.5, 8, 8.5, 8.5, 9, 9, 29.5, 9.5, 9.5, 10, 10, 9.5, 10, 0 );
		$rules   = array();
		foreach ( $amounts as $index => $amount ) {
			$rules[] = array(
				'from'   => 401 + 50 * $index,
				'amount' => $amount,
			);
		}
		return $rules;
	}

	/**
	 * Resolve a storefront selection key.
	 *
	 * @param array $field Size field definition.
	 * @return string
	 */
	public static function field_key( array $field ): string {
		return 'side_' . str_replace( '-', '_', sanitize_title( (string) ( $field['key'] ?? $field['label'] ?? '' ) ) );
	}

	/**
	 * Build defaults for a layout's current fields.
	 *
	 * @param array $fields Size field definitions.
	 * @return array
	 */
	public static function defaults( array $fields ): array {
		$dimensions = array();
		foreach ( $fields as $field ) {
			$dimensions[ self::field_key( $field ) ] = self::default_rules();
		}
		return array(
			'version'    => 1,
			'dimensions' => $dimensions,
			'shipping'   => array(
				'enabled' => true,
				'groups'  => array(
					array(
						'id'           => 'base',
						'amount'       => 36,
						'alternatives' => array(
							array(
								'first'  => 400,
								'second' => 400,
								'match'  => 'OR',
							),
						),
					),
					array(
						'id'           => 'large',
						'amount'       => 34,
						'alternatives' => array(
							array(
								'first'  => 1200,
								'second' => 700,
								'match'  => 'AND',
							),
							array(
								'first'  => 700,
								'second' => 1200,
								'match'  => 'AND',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Normalize settings; absent settings inherit defaults, empty lists remain empty.
	 *
	 * @param array $raw Raw settings.
	 * @param array $fields Current size fields.
	 * @return array
	 */
	public static function normalize( array $raw, array $fields ): array {
		$result = self::defaults( $fields );
		foreach ( $result['dimensions'] as $key => $default ) {
			if ( isset( $raw['dimensions'][ $key ] ) && is_array( $raw['dimensions'][ $key ] ) ) {
				$result['dimensions'][ $key ] = array();
				foreach ( $raw['dimensions'][ $key ] as $rule ) {
					if ( is_array( $rule ) && self::valid_number( $rule['from'] ?? null ) && self::valid_number( $rule['amount'] ?? null ) ) {
						$result['dimensions'][ $key ][] = array(
							'from'   => (float) $rule['from'],
							'amount' => (float) $rule['amount'],
						);
					}
				}
			}
		}
		if ( isset( $raw['shipping'] ) && is_array( $raw['shipping'] ) ) {
			$result['shipping']['enabled'] = ! empty( $raw['shipping']['enabled'] );
			if ( isset( $raw['shipping']['groups'] ) && is_array( $raw['shipping']['groups'] ) ) {
				$result['shipping']['groups'] = array();
				$base_seen                    = false;
				foreach ( $raw['shipping']['groups'] as $group ) {
					if ( ! is_array( $group ) || ! self::valid_number( $group['amount'] ?? null ) ) {
						continue;
					}
					$id = sanitize_key( (string) ( $group['id'] ?? '' ) );
					if ( 'base' === $id ) {
						if ( $base_seen ) {
							continue; }
						$base_seen = true;
					}
					$alternatives = array();
					foreach ( (array) ( $group['alternatives'] ?? array() ) as $condition ) {
						if ( is_array( $condition ) && self::valid_number( $condition['first'] ?? null ) && self::valid_number( $condition['second'] ?? null ) ) {
							$alternatives[] = array(
								'first'  => (float) $condition['first'],
								'second' => (float) $condition['second'],
								'match'  => 'OR' === ( $condition['match'] ?? '' ) ? 'OR' : 'AND',
							);
						}
					}
					$result['shipping']['groups'][] = array(
						'id'           => $id,
						'amount'       => (float) $group['amount'],
						'alternatives' => $alternatives,
					);
				}
			}
		}
		return $result;
	}

	/**
	 * Check a non-negative finite numeric value.
	 *
	 * @param mixed $value Candidate number.
	 * @return bool
	 */
	private static function valid_number( $value ): bool {
		return is_numeric( $value ) && is_finite( (float) $value ) && (float) $value >= 0;
	}

	/**
	 * Add every matching dimension surcharge.
	 *
	 * @param float $selected Measurement in mm.
	 * @param array $rules Threshold rules.
	 * @return float
	 */
	public static function dimension( float $selected, array $rules ): float {
		$total = 0.0;
		foreach ( $rules as $rule ) {
			if ( $selected >= $rule['from'] ) {
				$total += $rule['amount']; }
		}
		return $total;
	}

	/**
	 * Require real in-range dimensions before an amount can be charged.
	 *
	 * @param array $template Trusted layout.
	 * @param array $selections Customer selections.
	 * @return true|WP_Error
	 */
	public static function validate_selections( array $template, array $selections ) {
		foreach ( (array) ( $template['dimensions']['side_measurements'] ?? array() ) as $field ) {
			$value = $selections[ self::field_key( $field ) ] ?? $selections[ $field['key'] ] ?? null;
			if ( ! self::valid_number( $value ) || (float) $value < (float) $field['min'] || (float) $value > (float) $field['max'] ) {
				return new WP_Error(
					'wcs_invalid_dimension',
					sprintf(
					/* translators: 1: field label, 2: minimum, 3: maximum. */
						__( 'Bitte geben Sie für %1$s einen Wert zwischen %2$s und %3$s mm ein.', 'woo-spiegelloft-configurator' ),
						WCS_Storefront_Text::label( $field['label'] ),
						$field['min'],
						$field['max']
					)
				);
			}
		}
		return true;
	}

	/**
	 * Shipping's markup belongs exclusively to the base group.
	 *
	 * @param array $values Ordered dimensions, or a single diameter.
	 * @param float $base_price WooCommerce product price, excluding dimensions and extras.
	 * @param array $settings Shipping settings.
	 * @return array
	 */
	public static function shipping( array $values, float $base_price, array $settings ): array {
		$total   = 0.0;
		$matched = 0;
		if ( empty( $settings['enabled'] ) || count( $values ) < 1 || count( $values ) > 2 || in_array( null, $values, true ) ) {
			return array(
				'total'   => 0.0,
				'matched' => 0,
			);
		}
		$first     = $values[0];
		$second    = $values[1] ?? $first;
		$marked_up = false;
		foreach ( $settings['groups'] as $group ) {
			foreach ( $group['alternatives'] as $condition ) {
				$a = $first > $condition['first'];
				$b = $second > $condition['second'];
				if ( 'OR' === $condition['match'] ? ( $a || $b ) : ( $a && $b ) ) {
					$total += $group['amount'];
					if ( 'base' === $group['id'] && ! $marked_up ) {
						$total    += $base_price * 0.33;
						$marked_up = true; }
					++$matched;
					break;
				}
			}
		}
		return array(
			'total'   => $total,
			'matched' => $matched,
		);
	}

	/**
	 * Calculate dimension and shipping totals from trusted settings.
	 *
	 * @param array $template Trusted layout.
	 * @param array $selections Customer selections.
	 * @param float $base_price WooCommerce product price.
	 * @return array
	 */
	public static function calculate( array $template, array $selections, float $base_price = 0.0 ): array {
		$fields   = array_values( (array) ( $template['dimensions']['side_measurements'] ?? array() ) );
		$pricing  = self::normalize( (array) ( $template['pricing'] ?? array() ), $fields );
		$subtotal = 0.0;
		$values   = array();
		foreach ( $fields as $field ) {
			$key      = self::field_key( $field );
			$value    = $selections[ $key ] ?? $selections[ $field['key'] ] ?? null;
			$value    = self::valid_number( $value ) ? (float) $value : null;
			$values[] = $value;
			if ( null !== $value ) {
				$subtotal += self::dimension( $value, $pricing['dimensions'][ $key ] ); }
		}
		$shipping = self::shipping( $values, $base_price, $pricing['shipping'] );
		return array(
			'dimensions' => $subtotal,
			'shipping'   => $shipping['total'],
			'total'      => $subtotal + $shipping['total'],
		);
	}
}
