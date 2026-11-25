<?php
/**
 * Preserve destination image values without importing or downloading media.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

/** Matches destination images to surviving portable settings. */
class WCS_Layout_Import_Images {
	/**
	 * Restore only image settings that still have a corresponding field.
	 *
	 * @param array $incoming Imported settings, already stripped of source images.
	 * @param array $existing Current destination settings.
	 * @param array $groups Incoming field definitions.
	 * @return array
	 */
	public function preserve( array $incoming, array $existing, array $groups ): array {
		$keys = array(
			'image'      => true,
			'_wcs_image' => true,
		);
		$this->discover( $groups, $keys );
		return $this->merge( $incoming, $existing, $keys );
	}

	/**
	 * Find schema-declared image keys.
	 *
	 * @param array $tree Definitions.
	 * @param array $keys Image-key lookup, updated recursively.
	 */
	private function discover( array $tree, array &$keys ): void {
		foreach ( $tree as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( 'image' === ( $field['type'] ?? '' ) ) {
				$id = $field['id'] ?? ( is_string( $key ) ? $key : '' );
				if ( '' !== $id ) {
					$keys[ $id ] = true;
				}
			}
			$this->discover( $field, $keys );
		}
	}

	/**
	 * Merge images without restoring removed settings.
	 *
	 * @param array $incoming Incoming node.
	 * @param array $existing Corresponding destination node.
	 * @param array $keys Image-key lookup.
	 * @return array
	 */
	private function merge( array $incoming, array $existing, array $keys ): array {
		$is_list = array_values( $incoming ) === $incoming;
		if ( ! $is_list ) {
			foreach ( $keys as $image_key => $unused ) {
				if ( ! array_key_exists( $image_key, $incoming ) && isset( $existing[ $image_key ] ) && is_scalar( $existing[ $image_key ] ) ) {
					$incoming[ $image_key ] = $existing[ $image_key ];
				}
			}
			if ( 'image' === ( $incoming['type'] ?? '' ) && ! isset( $incoming['default'] ) && isset( $existing['default'] ) ) {
				$incoming['default'] = $existing['default'];
			}
		}
		foreach ( $incoming as $key => $value ) {
			$old = $existing[ $key ] ?? null;
			if ( is_array( $value ) ) {
				if ( $is_list ) {
					$old = $this->matching_row( $value, $existing, $key, $keys );
				}
				$incoming[ $key ] = $this->merge( $value, is_array( $old ) ? $old : array(), $keys );
			} elseif ( is_scalar( $old ) && ( isset( $keys[ $key ] ) || ( 'default' === $key && 'image' === ( $incoming['type'] ?? '' ) ) ) ) {
				$incoming[ $key ] = $old;
			}
		}
		return $incoming;
	}

	/**
	 * Match list rows by semantic identity, not their new display position.
	 *
	 * @param array $row Imported row.
	 * @param array $existing Destination rows.
	 * @param int   $index Imported index for anonymous unchanged repeaters.
	 * @param array $keys Image-key lookup.
	 * @return array
	 */
	private function matching_row( array $row, array $existing, int $index, array $keys ): array {
		foreach ( array( 'key', 'value', 'id', 'label', 'title' ) as $identity ) {
			if ( ! isset( $row[ $identity ] ) || ! is_scalar( $row[ $identity ] ) || '' === (string) $row[ $identity ] ) {
				continue;
			}
			$matches = array_filter(
				$existing,
				static function ( $old ) use ( $row, $identity ): bool {
					return is_array( $old ) && isset( $old[ $identity ] ) && (string) $old[ $identity ] === (string) $row[ $identity ];
				}
			);
			return 1 === count( $matches ) ? reset( $matches ) : array();
		}
		// Anonymous repeater rows can only retain images if their other values match.
		$old = $existing[ $index ] ?? array();
		if ( ! is_array( $old ) ) {
			return array();
		}
		$without_images = static function ( array $values ) use ( $keys ): array {
			return array_diff_key( $values, $keys );
		};
		return $without_images( $row ) === $without_images( $old ) ? $old : array();
	}
}
