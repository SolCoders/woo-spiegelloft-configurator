<?php
/**
 * Repeater extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Repeater
 */
class WCS_Extra_Field_Type_Repeater implements WCS_Extra_Field_Type {

	/**
	 * Field type registry.
	 *
	 * @var WCS_Extra_Field_Type_Registry
	 */
	private WCS_Extra_Field_Type_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param WCS_Extra_Field_Type_Registry $registry Field registry.
	 */
	public function __construct( WCS_Extra_Field_Type_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'repeater';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		$subfields = (array) ( $field['fields'] ?? array() );

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_data = array();
			foreach ( $subfields as $subfield ) {
				if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
					continue;
				}
				$id = (string) $subfield['id'];
				$row_data[ $id ] = $this->registry->sanitize_value( $row[ $id ] ?? '', $subfield );
			}
			$sanitized[] = $row_data;
		}

		return $sanitized;
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return true;
		}

		$subfields = (array) ( $field['fields'] ?? array() );
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $subfields as $subfield ) {
				if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
					continue;
				}
				$id     = (string) $subfield['id'];
				$result = $this->registry->validate_value( $row[ $id ] ?? '', $subfield );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function format_for_config( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$subfields = (array) ( $field['fields'] ?? array() );
		$formatted = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_data = array();
			foreach ( $subfields as $subfield ) {
				if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
					continue;
				}
				$id = (string) $subfield['id'];
				$handler = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
				$row_data[ $id ] = $handler
					? $handler->format_for_config( $row[ $id ] ?? '', $subfield )
					: $row[ $id ] ?? '';
			}
			$formatted[] = $row_data;
		}

		return $formatted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_field( string $name, $value, array $field ): void {
		$rows      = is_array( $value ) ? $value : array();
		$subfields = (array) ( $field['fields'] ?? array() );
		if ( empty( $rows ) ) {
			$rows = array( array() );
		}

		echo '<div class="wcs-repeater" data-name="' . esc_attr( $name ) . '">';
		foreach ( $rows as $index => $row ) {
			echo '<div class="wcs-repeater-row">';
			echo '<span class="wcs-repeater-handle dashicons dashicons-menu"></span>';
			echo '<div class="wcs-repeater-fields">';
			foreach ( $subfields as $subfield ) {
				if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
					continue;
				}
				$sub_name = $name . '[' . $index . '][' . $subfield['id'] . ']';
				$handler  = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
				if ( $handler ) {
					echo '<div class="wcs-repeater-subfield">';
					$handler->render_admin_field( $sub_name, is_array( $row ) ? ( $row[ $subfield['id'] ] ?? '' ) : '', $subfield );
					echo '</div>';
				}
			}
			echo '</div>';
			echo '<button type="button" class="button-link wcs-remove-repeater-row">' . esc_html__( 'Remove', 'woo-spiegelloft-configurator' ) . '</button>';
			echo '</div>';
		}
		echo '<button type="button" class="button wcs-add-repeater-row">' . esc_html__( 'Add row', 'woo-spiegelloft-configurator' ) . '</button>';
		echo '</div>';
	}
}