<?php
/**
 * Nested options extra field type.
 *
 * Supports a single option object (associative array) or a list of option
 * objects indexed numerically (mirror_type pattern).
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
	 * Whether value is a list of option rows.
	 *
	 * @param mixed $value Raw value.
	 */
	private function is_list_of_options( $value ): bool {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, mixed>|array<int, array<string, mixed>>
	 */
	public function sanitize( $value, array $field ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( $this->is_list_of_options( $value ) ) {
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
		$rows      = $this->is_list_of_options( $value ) ? $value : array( $value );

		foreach ( $rows as $row ) {
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

		if ( $this->is_list_of_options( $value ) ) {
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
					$id      = (string) $subfield['id'];
					$handler = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
					$row_data[ $id ] = $handler
						? $handler->format_for_config( $row[ $id ] ?? '', $subfield )
						: $row[ $id ] ?? '';
				}
				$formatted[] = $row_data;
			}
			return $formatted;
		}

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
		$subfields = (array) ( $field['fields'] ?? array() );

		if ( $this->is_list_of_options( $value ) || empty( $value ) ) {
			$rows = is_array( $value ) && ! empty( $value ) ? $value : array( array() );
			echo '<div class="wcs-nested-options wcs-nested-options-list" data-name="' . esc_attr( $name ) . '">';
			foreach ( $rows as $index => $row ) {
				$row = is_array( $row ) ? $row : array();
				echo '<div class="wcs-nested-option-row">';
				foreach ( $subfields as $subfield ) {
					if ( ! is_array( $subfield ) || empty( $subfield['id'] ) ) {
						continue;
					}
					$sub_name = $name . '[' . $index . '][' . $subfield['id'] . ']';
					$handler  = $this->registry->get( (string) ( $subfield['type'] ?? 'text' ) );
					if ( $handler ) {
						$handler->render_admin_field( $sub_name, $row[ $subfield['id'] ] ?? '', $subfield );
					}
				}
				echo '</div>';
			}
			echo '<button type="button" class="button wcs-add-nested-option-row">' . esc_html__( 'Add option', 'woo-spiegelloft-configurator' ) . '</button>';
			echo '</div>';
			return;
		}

		$data = is_array( $value ) ? $value : array();
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
