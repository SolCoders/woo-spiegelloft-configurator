<?php
/**
 * Import settings into a new layout or update the matching destination layout.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

/** Resolves destination records, remaps references and restores handled failures. */
class WCS_Layout_Importer {
	/**
	 * Portable package validator.
	 *
	 * @var WCS_Layout_Package
	 */
	private WCS_Layout_Package $package;
	/**
	 * Shared catalog cache.
	 *
	 * @var WCS_Cache
	 */
	private WCS_Cache $cache;

	/**
	 * Inject the portable validator and shared cache.
	 *
	 * @param WCS_Layout_Package $package Package validator.
	 * @param WCS_Cache          $cache Cache service.
	 */
	public function __construct( WCS_Layout_Package $package, WCS_Cache $cache ) {
		$this->package = $package;
		$this->cache   = $cache;
	}

	/**
	 * Name matching deliberately preserves punctuation and accents.
	 *
	 * @param string $name Stored or incoming title.
	 * @return string
	 */
	private function normalized_name( string $name ): string {
		$name = trim( preg_replace( '/[\s\p{Z}]+/u', ' ', $name ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtr(
			strtolower( $name ),
			array(
				'Ä' => 'ä',
				'Ö' => 'ö',
				'Ü' => 'ü',
				'ẞ' => 'ß',
			)
		);
	}

	/**
	 * Return all eligible matches rather than guessing between duplicate titles.
	 *
	 * @param string $title Incoming title.
	 * @return WP_Post[]
	 */
	public function matching_templates( string $title ): array {
		$name = $this->normalized_name( $title );
		return array_values(
			array_filter(
				get_posts(
					array(
						'post_type'      => 'wcs_template',
						'post_status'    => array( 'publish', 'draft' ),
						'posts_per_page' => -1,
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				),
				function ( WP_Post $post ) use ( $name ): bool {
					return $this->normalized_name( $post->post_title ) === $name;
				}
			)
		);
	}

	/**
	 * Import a package; source IDs are references, never destination identities.
	 *
	 * @param string   $json Uploaded JSON.
	 * @param int|null $target_template_id Explicit choice when names are ambiguous.
	 * @return array|WP_Error
	 * @throws RuntimeException Caught below; returned as WP_Error after rollback.
	 */
	public function import( string $json, ?int $target_template_id = null ) {
		$document = $this->package->decode( $json );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$document = $this->package->sanitize_tree( $document );
		if ( '' === $this->normalized_name( $document['template']['title'] ) ) {
			return new WP_Error( 'wcs_layout_title', __( 'The layout title is required.', 'woo-spiegelloft-configurator' ) );
		}
		$matches   = $this->matching_templates( $document['template']['title'] );
		$match_ids = array_map( static fn( WP_Post $post ): int => (int) $post->ID, $matches );
		if ( null !== $target_template_id && ! in_array( $target_template_id, $match_ids, true ) ) {
			return new WP_Error( 'wcs_layout_target', __( 'The selected template no longer matches this upload. Upload the file again.', 'woo-spiegelloft-configurator' ) );
		}
		if ( null === $target_template_id && count( $matches ) > 1 ) {
			return new WP_Error(
				'wcs_layout_ambiguous',
				__( 'Several templates have this name. Choose the template to update.', 'woo-spiegelloft-configurator' ),
				array(
					'matches' => array_map(
						static function ( WP_Post $post ): array {
							return array(
								'id'     => (int) $post->ID,
								'title'  => $post->post_title,
								'status' => $post->post_status,
							);
						},
						$matches
					),
				)
			);
		}
		$target_id     = $target_template_id ?? ( $match_ids[0] ?? 0 );
		$created       = array();
		$backups       = array();
		$lock_key      = 'wcs_layout_import_' . md5( $this->normalized_name( $document['template']['title'] ) );
		$lock          = array(
			'token'   => wp_generate_uuid4(),
			'expires' => time() + 10 * MINUTE_IN_SECONDS,
		);
		$previous_lock = get_option( $lock_key );
		if ( is_array( $previous_lock ) && ( $previous_lock['expires'] ?? PHP_INT_MAX ) < time() ) {
			delete_option( $lock_key );
		}
		if ( ! add_option( $lock_key, $lock, '', false ) ) {
			return new WP_Error( 'wcs_layout_busy', __( 'An import for this template is already running. Please try again after it finishes.', 'woo-spiegelloft-configurator' ) );
		}
		try {
			// Detect destination changes after taking the per-name import lock.
			if ( array_map( static fn( WP_Post $post ): int => (int) $post->ID, $this->matching_templates( $document['template']['title'] ) ) !== $match_ids ) {
				throw new RuntimeException( __( 'The matching templates changed. Please upload the file again.', 'woo-spiegelloft-configurator' ) );
			}
			if ( $target_id && ! current_user_can( 'edit_post', $target_id ) ) {
				throw new RuntimeException( __( 'You cannot update this template.', 'woo-spiegelloft-configurator' ) );
			}
			$template         = new WCS_Template( $this->cache );
			$catalog          = new WCS_Extras_Catalog( $this->cache );
			$registry         = new WCS_Extras_Registry();
			$images           = new WCS_Layout_Import_Images();
			$old_settings     = $target_id ? $template->get_template_data( $target_id ) : array();
			$old_dependencies = $target_id ? (array) get_post_meta( $target_id, '_wcs_template_dependencies', true ) : array();
			if ( $target_id && empty( $old_dependencies['groups'] ) ) {
				$old_dependencies['groups'] = $registry->get_groups_for_template( $target_id );
			}
			$candidates          = $target_id ? $this->template_options( $target_id, $template, $catalog, $registry ) : array();
			$selected_candidates = $candidates;
			// Reuse private, previously detached imports if they are reintroduced later.
			if ( $target_id ) {
				foreach ( get_posts(
					array(
						'post_type'      => 'wcs_extra_option',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => '_wcs_import_owner', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Scoped ownership lookup for detached import options.
						'meta_value'     => $target_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Scoped ownership lookup.
					)
				) as $owned ) {
					$candidates[ $owned ] = true;
				}
			}
			$candidate_options = array();
			foreach ( array_keys( $candidates ) as $candidate_id ) {
				$option = $catalog->get_option( (int) $candidate_id );
				if ( $option ) {
					$candidate_options[ $candidate_id ] = $option;
				}
			}
			$shared = array();
			if ( $candidates ) {
				foreach ( get_posts(
					array(
						'post_type'      => 'wcs_template',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				) as $other_id ) {
					if ( (int) $other_id !== $target_id ) {
						$shared += array_intersect_key( $this->template_options( (int) $other_id, $template, $catalog, $registry ), $candidates );
					}
				}
			}
			// Resolve every option before writing anything. Never use global title matches.
			$plan = array();
			$used = array();
			foreach ( $document['options'] as $option ) {
				$existing = $this->match_option( $option, array_intersect_key( $candidate_options, $selected_candidates ) );
				if ( ! $existing ) {
					$existing = $this->match_option( $option, array_diff_key( $candidate_options, $shared ) );
				}
				if ( ! $existing ) {
					$existing = $this->match_option( $option, $candidate_options );
				}
				if ( $existing && isset( $used[ $existing ] ) ) {
					throw new RuntimeException( __( 'Two imported options match the same destination option.', 'woo-spiegelloft-configurator' ) );
				}
				if ( $existing ) {
					$used[ $existing ] = true;
				}
				$reuse = $existing && ! isset( $shared[ $existing ] ) ? $existing : 0;
				if ( $reuse && ! current_user_can( 'edit_post', $reuse ) ) {
					throw new RuntimeException( __( 'You cannot update a customer option used by this template.', 'woo-spiegelloft-configurator' ) );
				}
				$meta = $option['meta'];
				if ( $existing ) {
					$old_meta = array();
					foreach ( get_post_meta( $existing ) as $key => $values ) {
						$old_meta[ $key ] = maybe_unserialize( $values[0] );
					}
					// An omitted root image still belongs to a matching existing option.
					if ( ! isset( $meta['_wcs_image'] ) && isset( $old_meta['_wcs_image'] ) ) {
						$meta['_wcs_image'] = '';
					}
					$meta = $images->preserve( $meta, $old_meta, $document['groups'] );
				}
				$plan[] = array(
					'source'   => $option,
					'existing' => $existing,
					'reuse'    => $reuse,
					'meta'     => $meta,
				);
			}
			if ( $target_id ) {
				$backups[ $target_id ] = $this->snapshot( $target_id );
			}
			if ( ! $target_id ) {
				$target_id = wp_insert_post(
					array(
						'post_type'   => 'wcs_template',
						'post_title'  => wp_slash( $document['template']['title'] ),
						'post_status' => 'draft',
					),
					true
				);
				$this->check_result( $target_id );
				$created[] = $target_id;
			}
			$ids       = array();
			$local_ids = array();
			foreach ( $plan as $entry ) {
				$option = $entry['source'];
				$id     = $entry['reuse'];
				if ( $id ) {
					$backups[ $id ] = $this->snapshot( $id );
					$this->check_result(
						wp_update_post(
							array(
								'ID'         => $id,
								'post_title' => wp_slash( $option['title'] ),
							),
							true
						)
					);
				} else {
					// Keep copies outside shared taxonomies so implicit selections do not grow.
					$id = wp_insert_post(
						array(
							'post_type'   => 'wcs_extra_option',
							'post_title'  => wp_slash( $option['title'] ),
							'post_status' => 'publish',
						),
						true
					);
					$this->check_result( $id );
					$created[] = $id;
				}
				$ids[ $option['id'] ] = $id;
				if ( $entry['existing'] ) {
					$local_ids[ $entry['existing'] ] = $id;
				}
				foreach ( array( '_wcs_option_data', '_wcs_option_slug', '_wcs_price', '_wcs_legacy_id', '_wcs_image' ) as $key ) {
					if ( ! array_key_exists( $key, $entry['meta'] ) ) {
						delete_post_meta( $id, $key );
						if ( metadata_exists( 'post', $id, $key ) ) {
							throw new RuntimeException( __( 'Unable to remove obsolete imported settings.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The admin view escapes returned error text.
						}
					}
				}
				foreach ( $entry['meta'] as $key => $value ) {
					$this->save_meta( $id, $key, $value );
				}
				$this->save_meta( $id, '_wcs_option_slug', $option['slug'] );
				$this->save_meta( $id, '_wcs_import_owner', $target_id );
			}
			$document = $this->remap_groups( $document, $ids );
			foreach ( $document['options'] as $option ) {
				$this->save_meta( $ids[ $option['id'] ], '_wcs_import_group', $option['group'] );
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
			$updating = isset( $backups[ $target_id ] );
			if ( $updating ) {
				$settings['slug'] = $old_settings['slug'] ?? get_post_field( 'post_name', $target_id );
				$settings         = $images->preserve( $settings, (array) $old_settings, $document['groups'] );
			} else {
				$slug             = sanitize_title( $settings['slug'] ?? $document['template']['title'] );
				$settings['slug'] = wp_unique_post_slug( ( $slug ? $slug : 'mirror-layout' ) . '-' . $target_id, $target_id, 'publish', 'wcs_template', 0 );
			}
			unset( $settings['id'], $settings['title'], $settings['dependencies'], $settings['groups'], $settings['rules'] );
			$this->save_meta( $target_id, '_wcs_template_data', $settings );
			$dependencies = array(
				'groups'    => $document['groups'],
				'positions' => $document['positions'],
			);
			if ( $updating ) {
				// Include legacy local/global position settings when no dependency snapshot exists yet.
				$old_dependencies['positions'] = $template->get_position_settings( $target_id );
				// A private copy changes synthetic group keys but keeps the same local images.
				foreach ( array( 'groups', 'positions' ) as $section ) {
					$mapped = array();
					foreach ( (array) ( $old_dependencies[ $section ] ?? array() ) as $key => $definition ) {
						$key            = preg_replace_callback( '/__choice_(\d+)$/', static fn( array $reference ): string => isset( $local_ids[ (int) $reference[1] ] ) ? '__choice_' . $local_ids[ (int) $reference[1] ] : $reference[0], $key );
						$mapped[ $key ] = $definition;
					}
					$old_dependencies[ $section ] = $mapped;
				}
				$dependencies = $images->preserve( $dependencies, $old_dependencies, $document['groups'] );
			}
			$this->save_meta( $target_id, '_wcs_template_dependencies', $dependencies );
			foreach ( $ids as $id ) {
				$this->save_meta( $id, '_wcs_group_snapshots', $dependencies['groups'] );
			}
			if ( ! $updating ) {
				$this->check_result(
					wp_update_post(
						array(
							'ID'          => $target_id,
							'post_name'   => $settings['slug'],
							'post_status' => $document['template']['status'],
						),
						true
					)
				);
			}
			$this->cache->bump_version();
			return array(
				'template_id' => $target_id,
				'options'     => count( $ids ),
				'action'      => $updating ? 'updated' : 'created',
			);
		} catch ( Throwable $error ) {
			$failed = array();
			foreach ( array_reverse( $created ) as $id ) {
				if ( ! wp_delete_post( $id, true ) ) {
					$failed[] = $id;
				}
			}
			foreach ( array_reverse( $backups, true ) as $id => $backup ) {
				try {
					$this->restore( (int) $id, $backup );
				} catch ( Throwable $restore_error ) {
					$failed[] = $id;
				}
			}
			$this->cache->bump_version();
			$message = $error->getMessage();
			if ( $failed ) {
				$message .= ' Cleanup or restoration failed for IDs: ' . implode( ', ', $failed );
			}
			return new WP_Error( 'wcs_layout_import', $message );
		} finally {
			if ( get_option( $lock_key ) === $lock ) {
				delete_option( $lock_key );
			}
		}
	}

	/**
	 * Resolve effective selections, including implicit legacy selections.
	 *
	 * @param int                 $id Template ID.
	 * @param WCS_Template        $template Template service.
	 * @param WCS_Extras_Catalog  $catalog Catalog service.
	 * @param WCS_Extras_Registry $registry Definition registry.
	 * @return array Option-ID lookup.
	 */
	private function template_options( int $id, WCS_Template $template, WCS_Extras_Catalog $catalog, WCS_Extras_Registry $registry ): array {
		$data = $template->get_template_data( $id );
		if ( ! $data ) {
			return array();
		}
		$result = array();
		foreach ( $catalog->get_template_option_map( $data, $registry->get_groups_for_template( $id ) ) as $selected ) {
			foreach ( $selected as $option_id ) {
				$result[ $option_id ] = true;
			}
		}
		return $result;
	}

	/**
	 * Match an option within its destination template.
	 *
	 * @param array $incoming Portable option.
	 * @param array $candidates Destination options keyed by ID.
	 * @return int
	 * @throws RuntimeException When the option identity is ambiguous.
	 */
	private function match_option( array $incoming, array $candidates ): int {
		$group   = array_filter( $candidates, static fn( array $option ): bool => preg_replace( '/__choice_\d+$/', '', $option['group'] ) === preg_replace( '/__choice_\d+$/', '', $incoming['group'] ) );
		$matches = array_filter( $group, static fn( array $option ): bool => '' !== $incoming['slug'] && $option['slug'] === $incoming['slug'] );
		if ( ! $matches ) {
			$name    = $this->normalized_name( $incoming['title'] );
			$matches = array_filter( $group, fn( array $option ): bool => $this->normalized_name( $option['title'] ) === $name );
		}
		if ( count( $matches ) > 1 ) {
			throw new RuntimeException( __( 'Several existing customer options match an imported option: ', 'woo-spiegelloft-configurator' ) . $incoming['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The admin view escapes returned error text.
		}
		return $matches ? (int) array_key_first( $matches ) : 0;
	}

	/**
	 * Remap synthetic groups along with the option references they contain.
	 *
	 * @param array $document Validated document.
	 * @param array $ids Source-to-destination option IDs.
	 * @return array
	 */
	private function remap_groups( array $document, array $ids ): array {
		$slug = static function ( string $value ) use ( $ids ): string {
			return preg_replace_callback( '/__choice_(\d+)$/', static fn( array $reference ): string => isset( $ids[ (int) $reference[1] ] ) ? '__choice_' . $ids[ (int) $reference[1] ] : $reference[0], $value );
		};
		$keys = static function ( array $values ) use ( $slug ): array {
			$result = array();
			foreach ( $values as $key => $value ) {
				$result[ $slug( (string) $key ) ] = $value;
			}
			return $result;
		};
		foreach ( array( 'enabled_groups', 'group_order' ) as $key ) {
			$document['template']['data'][ $key ] = array_map( $slug, $document['template']['data'][ $key ] );
		}
		foreach ( array( 'step_map', 'extra_option_map' ) as $key ) {
			$document['template']['data'][ $key ] = $keys( $document['template']['data'][ $key ] );
		}
		$document['groups'] = $keys( $document['groups'] );
		foreach ( $document['groups'] as $key => &$definition ) {
			$definition['slug'] = $key;
		}
		unset( $definition );
		$document['positions'] = $keys( $document['positions'] );
		foreach ( $document['options'] as &$option ) {
			$option['group'] = $slug( $option['group'] );
		}
		unset( $option );
		return $document;
	}

	/**
	 * Capture fields this importer may change, leaving unrelated metadata alone.
	 *
	 * @param int $id Destination record.
	 * @return array
	 */
	private function snapshot( int $id ): array {
		$post = get_post( $id );
		return array(
			'post' => array(
				'ID'          => $id,
				'post_title'  => $post->post_title,
				'post_name'   => $post->post_name,
				'post_status' => $post->post_status,
			),
			'meta' => array_filter( get_post_meta( $id ), static fn( string $key ): bool => 0 === strpos( $key, '_wcs_' ), ARRAY_FILTER_USE_KEY ),
		);
	}

	/**
	 * Restore and verify an existing destination record.
	 *
	 * @param int   $id Destination record.
	 * @param array $backup Captured post fields and plugin metadata.
	 * @throws RuntimeException When restoration cannot be verified.
	 */
	private function restore( int $id, array $backup ): void {
		$this->check_result( wp_update_post( wp_slash( $backup['post'] ), true ) );
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 === strpos( $key, '_wcs_' ) ) {
				delete_post_meta( $id, $key );
			}
		}
		foreach ( $backup['meta'] as $key => $values ) {
			foreach ( $values as $value ) {
				$this->check_result( add_post_meta( $id, $key, wp_slash( maybe_unserialize( $value ) ) ) );
			}
		}
		$restored = $this->snapshot( $id );
		if ( $restored !== $backup ) {
			throw new RuntimeException( 'Restored settings do not match the original.' );
		}
	}

	/**
	 * Turn persistence failures into handled importer exceptions.
	 *
	 * @param mixed $result WordPress result.
	 * @throws RuntimeException On an unsuccessful result.
	 */
	private function check_result( $result ): void {
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The admin view escapes returned error text.
		}
		if ( ! $result ) {
			throw new RuntimeException( __( 'WordPress could not save the imported content.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The admin view escapes returned error text.
		}
	}

	/**
	 * Persist slashed metadata and verify unchanged-value returns.
	 *
	 * @param int    $id Destination record.
	 * @param string $key Metadata key.
	 * @param mixed  $value Imported setting.
	 * @throws RuntimeException When the setting could not be saved.
	 */
	private function save_meta( int $id, string $key, $value ): void {
		$result = update_post_meta( $id, $key, wp_slash( $value ) );
		if ( false === $result && get_post_meta( $id, $key, true ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- WordPress stores scalar metadata as strings.
			throw new RuntimeException( __( 'Unable to save imported settings: ', 'woo-spiegelloft-configurator' ) . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The admin view escapes returned error text.
		}
	}
}
