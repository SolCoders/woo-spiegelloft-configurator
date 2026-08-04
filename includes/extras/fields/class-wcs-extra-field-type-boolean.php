<?php
/**
 * Boolean extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Boolean
 */
class WCS_Extra_Field_Type_Boolean implements WCS_Extra_Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'boolean';
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize( $value, array $field ) {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( $value, array $field ) {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function format_for_config( $value, array $field ) {
		return (bool) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_field( string $name, $value, array $field ): void {
		$label   = esc_html( (string) ( $field['label'] ?? $field['id'] ?? '' ) );
		$checked = rest_sanitize_boolean( $value ) ? 'checked' : '';
		printf(
			'<p><label><input type="checkbox" name="%1$s" value="1" %2$s> <strong>%3$s</strong></label></p>',
			esc_attr( $name ),
			$checked,
			$label
		);
	}
}