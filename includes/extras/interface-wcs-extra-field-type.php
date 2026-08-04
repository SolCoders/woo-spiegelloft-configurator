<?php
/**
 * Extra field type interface.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Interface WCS_Extra_Field_Type
 */
interface WCS_Extra_Field_Type {

	/**
	 * Field type identifier.
	 */
	public function get_type(): string;

	/**
	 * Sanitize submitted value.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	public function sanitize( $value, array $field );

	/**
	 * Validate submitted value.
	 *
	 * @param mixed                $value Sanitized value.
	 * @param array<string, mixed> $field Field definition.
	 * @return true|WP_Error
	 */
	public function validate( $value, array $field );

	/**
	 * Format value for config JSON output.
	 *
	 * @param mixed                $value Stored value.
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	public function format_for_config( $value, array $field );

	/**
	 * Render admin field markup.
	 *
	 * @param string               $name  Input name.
	 * @param mixed                $value Current value.
	 * @param array<string, mixed> $field Field definition.
	 */
	public function render_admin_field( string $name, $value, array $field ): void;
}