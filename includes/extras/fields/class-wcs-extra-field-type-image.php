<?php
/**
 * Image extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Image
 */
class WCS_Extra_Field_Type_Image implements WCS_Extra_Field_Type {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'image';
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize( $value, array $field ) {
		return esc_url_raw( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( $value, array $field ) {
		if ( ! empty( $field['required'] ) && '' === (string) $value ) {
			return new WP_Error( 'wcs_required', __( 'Image URL is required.', 'woo-spiegelloft-configurator' ) );
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
		$label = esc_html( (string) ( $field['label'] ?? 'Image URL' ) );
		printf(
			'<p class="wcs-image-field"><label><strong>%1$s</strong><br><input type="url" name="%2$s" value="%3$s" class="widefat wcs-image-url"><button type="button" class="button wcs-upload-image" data-target="%2$s">%4$s</button></label></p>',
			$label,
			esc_attr( $name ),
			esc_attr( (string) $value ),
			esc_html__( 'Upload', 'woo-spiegelloft-configurator' )
		);
	}
}