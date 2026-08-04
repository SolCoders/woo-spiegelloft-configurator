<?php
/**
 * Nested options extra field type.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extra_Field_Type_Nested_Options
 */
class WCS_Extra_Field_Type_Nested_Options implements WCS_Extra_Field_Type {

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
		return 'nested_options';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		$subfields = (array) ( $field['fields'] ?? array() );

		foreach ( $subfields as $subfield ) {
			if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
				continue;
			}
			$id = (string) $subfield['id'];
			$sanitized[ $id ] = $this->registry->sanitize_value( $value[ $id ] ?? '', $subfield );
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
		foreach ( $subfields as $subfield ) {
			if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
				continue;
			}
			$id     = (string) $subfield['id'];
			$result = $this->registry->validate_value( $value[ $id ] ?? '', $subfield );
			if ( is_wp_error( $result ) ) {
				return $result;
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

		foreach ( $subfields as $subfield ) {
			if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
				continue;
			}
			$id      = (string) $subfield['id'];
			$handler = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
			$formatted[ $id ] = $handler
				? $handler->format_for_config( $value[ $id ] ?? '', $subfield )
				: $value[ $id ] ?? '';
		}

		return $formatted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_field( string $name, $value, array $field ): void {
		$data      = is_array( $value ) ? $value : array();
		$subfields = (array) ( $field['fields'] ?? array() );
		echo '<div class="wcs-nested-options">';
		foreach ( $subfields as $subfield ) {
			if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
				continue;
			}
			$sub_name = $name . '[' . $subfield['id'] . ']';
			$handler  = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
			if ( $handler ) {
				$handler->render_admin_field( $sub_name, $data[ $subfield['id'] ] ?? '', $subfield );
			}
		}
		echo '</div>';
	}
}