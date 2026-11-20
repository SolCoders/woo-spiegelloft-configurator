<?php
/**
 * Creates independent layouts and rolls back handled failures.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** Imports only new records; never updates source IDs or existing content. */
class WCS_Layout_Importer {

	/**
	 * Package service or configuration.
	 *
	 * @var WCS_Layout_Package
	 */
	private WCS_Layout_Package $package;
	/**
	 * Cache service or configuration.
	 *
	 * @var WCS_Cache
	 */
	private WCS_Cache $cache;

	/**
	 * Inject package, media and cache services.
	 *
	 * @param WCS_Layout_Package $package Package.
	 * @param WCS_Cache          $cache Cache.
	 */
	public function __construct( WCS_Layout_Package $package, WCS_Cache $cache ) {
		$this->package = $package;
		$this->cache   = $cache;
	}

	/**
	 * Import a validated document.
	 *
	 * @param string $json Json.
	 * @return array|WP_Error
	 * @throws RuntimeException Internally caught and returned as WP_Error.
	 */
	public function import( string $json ) {
		$document = $this->package->decode( $json );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$created = array();
		try {
			$document = $this->package->sanitize_tree( $document );
			$ids      = array();
			foreach ( $document['options'] as $option ) {
				// Do not add copies to existing taxonomies: older layouts may select all members.
				$id = wp_insert_post(
					array(
						'post_type'   => 'wcs_extra_option',
						'post_title'  => wp_slash( $option['title'] ),
						'post_status' => 'publish',
					),
					true
				);
				$this->check_result( $id );
				$created[]            = $id;
				$ids[ $option['id'] ] = $id;
				foreach ( $option['meta'] as $key => $value ) {
					$this->save_meta( $id, $key, $value );
				}
				$this->save_meta( $id, '_wcs_option_slug', $option['slug'] );
				$this->save_meta( $id, '_wcs_import_group', $option['group'] );
				$this->save_meta( $id, '_wcs_group_snapshots', $document['groups'] );
			}
			$settings = $document['template']['data'];
			foreach ( $settings['extra_option_map'] as $group => $selected ) {
				$settings['extra_option_map'][ $group ] = array_map( static fn( int $id ): int => $ids[ $id ], $selected );
			}
			$settings['choice_order'] = array_map( static fn( int $id ): int => $ids[ $id ], $settings['choice_order'] );
			$steps                    = array();
			foreach ( $settings['choice_step_map'] as $id => $step ) {
				$steps[ $ids[ $id ] ] = $step;
			}
			$settings['choice_step_map'] = $steps;
			foreach ( array( 'validation_rules', 'behavior_rules' ) as $key ) {
				$settings[ $key ] = $this->package->remap_rules( $settings[ $key ], $ids );
			}
			$id = wp_insert_post(
				array(
					'post_type'   => 'wcs_template',
					'post_title'  => wp_slash( $document['template']['title'] ),
					'post_status' => 'draft',
				),
				true
			);
			$this->check_result( $id );
			$created[] = $id;
			$slug      = sanitize_title( $settings['slug'] ?? $document['template']['title'] );
			// The ID suffix also makes draft slugs unique (WordPress otherwise permits duplicates).
			$settings['slug'] = wp_unique_post_slug( ( $slug ? $slug : 'mirror-layout' ) . '-' . $id, $id, 'publish', 'wcs_template', 0 );
			unset( $settings['id'], $settings['title'], $settings['dependencies'], $settings['groups'], $settings['rules'] );
			$this->save_meta( $id, '_wcs_template_data', $settings );
			$this->save_meta(
				$id,
				'_wcs_template_dependencies',
				array(
					'groups'    => $document['groups'],
					'positions' => $document['positions'],
				)
			);
			$this->check_result(
				wp_update_post(
					array(
						'ID'          => $id,
						'post_name'   => $settings['slug'],
						'post_status' => $document['template']['status'],
					),
					true
				)
			);
			$this->cache->bump_version();
			return array(
				'template_id' => $id,
				'options'     => count( $ids ),
			);
		} catch ( Throwable $error ) {
			$failed = array();
			foreach ( array_reverse( $created ) as $id ) {
				if ( ! wp_delete_post( $id, true ) ) {
					$failed[] = $id;
				}
			}
			$this->cache->bump_version();
			$message = $error->getMessage();
			if ( $failed ) {
				$message .= ' Cleanup failed for newly created IDs: ' . implode( ', ', $failed );
			}
			return new WP_Error( 'wcs_layout_import', $message );
		}
	}

	/**
	 * Check WordPress insert/update results.
	 *
	 * @param mixed $result Result.
	 * @throws RuntimeException When processing fails.
	 */
	private function check_result( $result ): void {
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
		}
		if ( ! $result ) {
			throw new RuntimeException( __( 'WordPress could not create the imported content.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
		}
	}

	/**
	 * Store slashed metadata and verify failures rather than silently losing settings.
	 *
	 * @param int    $id Id.
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @throws RuntimeException When processing fails.
	 */
	private function save_meta( int $id, string $key, $value ): void {
		$result = update_post_meta( $id, $key, wp_slash( $value ) );
		if ( false === $result && get_post_meta( $id, $key, true ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- WP stores scalar metadata as strings.
			throw new RuntimeException( __( 'Unable to save imported settings: ', 'woo-spiegelloft-configurator' ) . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
		}
	}
}
