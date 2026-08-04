<?php
/**
 * Text extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Text
 */
class WCS_Extra_Field_Type_Text implements WCS_Extra_Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'text';
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize( $value, array $field ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( $value, array $field ) {
		if ( ! empty( $field['required'] ) && '' === (string) $value ) {
			return new WP_Error( 'wcs_required', __( 'This field is required.', 'woo-spiegelloft-configurator' ) );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function format_for_config( $value, array $field ) {
		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_field( string $name, $value, array $field ): void {
		$label = esc_html( (string) ( $field['label'] ?? $field['id'] ?? '' ) );
		printf(
			'<p><label><strong>%1$s</strong><br><input type="text" name="%2$s" value="%3$s" class="widefat"></label></p>',
			$label,
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
	}
}