<?php
/**
 * Registry for extra field types.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once WCS_PLUGIN_DIR . 'includes/extras/interface-wcs-extra-field-type.php';

/**
 * Class WCS_Extra_Field_Type_Registry
 */
class WCS_Extra_Field_Type_Registry {

	/**
	 * Registered field types.
	 *
	 * @var array<string, WCS_Extra_Field_Type>
	 */
	private array $types = array();

	/**
	 * Register default field types.
	 */
	public function register_defaults(): void {
		$this->register( new WCS_Extra_Field_Type_Text() );
		$this->register( new WCS_Extra_Field_Type_Price() );
		$this->register( new WCS_Extra_Field_Type_Image() );
		$this->register( new WCS_Extra_Field_Type_Boolean() );
		$this->register( new WCS_Extra_Field_Type_Repeater( $this ) );
		$this->register( new WCS_Extra_Field_Type_Nested_Options( $this ) );
	}

	/**
	 * Register a field type.
	 *
	 * @param WCS_Extra_Field_Type $type Field type instance.
	 */
	public function register( WCS_Extra_Field_Type $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	/**
	 * Get field type by identifier.
	 *
	 * @param string $type Type identifier.
	 */
	public function get( string $type ): ?WCS_Extra_Field_Type {
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Get all registered types.
	 *
	 * @return array<string, WCS_Extra_Field_Type>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Sanitize a field value using its type handler.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, array $field ) {
		$handler = $this->get( (string) ( $field['type'] ?? 'text' ) );
		if ( ! $handler ) {
			return sanitize_text_field( (string) $value );
		}
		return $handler->sanitize( $value, $field );
	}

	/**
	 * Validate a field value using its type handler.
	 *
	 * @param mixed                $value Sanitized value.
	 * @param array<string, mixed> $field Field definition.
	 * @return true|WP_Error
	 */
	public function validate_value( $value, array $field ) {
		$handler = $this->get( (string) ( $field['type'] ?? 'text' ) );
		if ( ! $handler ) {
			return true;
		}
		return $handler->validate( $value, $field );
	}
}