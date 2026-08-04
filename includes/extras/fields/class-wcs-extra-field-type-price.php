<?php
/**
 * Price extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Price
 */
class WCS_Extra_Field_Type_Price implements WCS_Extra_Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'price';
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize( $value, array $field ) {
		return wc_format_decimal( (string) $value, wc_get_price_decimals() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( $value, array $field ) {
		if ( ! is_numeric( $value ) ) {
			return new WP_Error( 'wcs_invalid_price', __( 'Invalid price value.', 'woo-spiegelloft-configurator' ) );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function format_for_config( $value, array $field ) {
		return (float) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_field( string $name, $value, array $field ): void {
		$label = esc_html( (string) ( $field['label'] ?? 'Price' ) );
		printf(
			'<p><label><strong>%1$s</strong><br><input type="number" step="0.01" min="0" name="%2$s" value="%3$s" class="widefat"></label></p>',
			$label,
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
	}
}