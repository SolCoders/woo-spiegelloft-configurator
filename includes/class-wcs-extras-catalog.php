<?php
/**
 * Extras catalog CPT management.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extras_Catalog
 */
class WCS_Extras_Catalog {

	/**
	 * Cache helper.
	 *
	 * @var WCS_Cache
	 */
	private WCS_Cache $cache;

	/**
	 * Constructor.
	 *
	 * @param WCS_Cache $cache Cache instance.
	 */
	public function __construct( WCS_Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Register hooks and CPT.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 10 );
		add_action( 'init', array( $this, 'register_taxonomy' ), 10 );
		add_action( 'save_post_wcs_extra_option', array( $this, 'invalidate_cache' ), 10, 1 );
	}

	/**
	 * Register wcs_extra_option CPT.
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcs_extra_option',
			array(
				'labels'              => array(
					'name'          => __( 'Customization choices', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Customization choice', 'woo-spiegelloft-configurator' ),
					'add_new_item'  => __( 'Add customization choice', 'woo-spiegelloft-configurator' ),
					'edit_item'     => __( 'Edit customization choice', 'woo-spiegelloft-configurator' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}

	/**
	 * Register wcs_extra_group taxonomy.
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			'wcs_extra_group',
			'wcs_extra_option',
			array(
				'labels'            => array(
					'name'          => __( 'Extra Groups', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Extra Group', 'woo-spiegelloft-configurator' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Invalidate catalog cache on save.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( int $post_id ): void {
		unset( $post_id );
		$this->cache->bump_version();
	}

	/**
	 * Get all published options regardless of group assignment.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_options(): array {
		$cache_key = 'extras_all';
		$cached    = $this->cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wcs_extra_option',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$options[] = $this->format_option( $post );
		}

		$this->cache->set( $cache_key, $options );
		return $options;
	}

	/**
	 * Get all options for a group slug.
	 *
	 * @param string $group_slug Group slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_options_by_group( string $group_slug ): array {
		$cache_key = 'extras_group_' . $group_slug;
		$cached    = $this->cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$term = get_term_by( 'slug', $group_slug, 'wcs_extra_group' );
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wcs_extra_option',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'wcs_extra_group',
						'field'    => 'term_id',
						'terms'    => (int) $term->term_id,
					),
				),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$options[] = $this->format_option( $post );
		}

		$this->cache->set( $cache_key, $options );
		return $options;
	}

	/**
	 * Get published options that are not assigned to any group.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_uncategorized_options(): array {
		$cache_key = 'extras_uncategorized';
		$cached    = $this->cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wcs_extra_option',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'wcs_extra_group',
						'operator' => 'NOT EXISTS',
					),
				),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$options[] = $this->format_option( $post );
		}

		$this->cache->set( $cache_key, $options );
		return $options;
	}

	/**
	 * Get group options plus selected options that may not belong to the group.
	 *
	 * @param string $group_slug Group slug.
	 * @param int[]  $option_ids Selected option IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_options_for_template_group( string $group_slug, array $option_ids = array() ): array {
		$options = $this->get_options_by_group( $group_slug );
		if ( empty( $option_ids ) ) {
			return $options;
		}

		$seen = array();
		foreach ( $options as $option ) {
			$seen[ (int) ( $option['id'] ?? 0 ) ] = true;
		}

		foreach ( array_map( 'absint', $option_ids ) as $option_id ) {
			if ( ! $option_id || isset( $seen[ $option_id ] ) ) {
				continue;
			}

			$option = $this->get_option( $option_id );
			if ( $option ) {
				$options[]            = $option;
				$seen[ $option_id ] = true;
			}
		}

		return $options;
	}

	/**
	 * Get a single option by ID.
	 *
	 * @param int $option_id Option post ID.
	 * @return array<string, mixed>|null
	 */
	public function get_option( int $option_id ): ?array {
		$post = get_post( $option_id );
		if ( ! $post || 'wcs_extra_option' !== $post->post_type ) {
			return null;
		}
		return $this->format_option( $post );
	}

	/**
	 * Format option post as array.
	 *
	 * @param WP_Post $post Option post.
	 * @return array<string, mixed>
	 */
	public function format_option( WP_Post $post ): array {
		$meta = get_post_meta( $post->ID );
		$flat = array();
		foreach ( $meta as $key => $values ) {
			if ( 0 === strpos( $key, '_wcs_' ) ) {
				$flat[ $key ] = maybe_unserialize( $values[0] ?? '' );
			}
		}

		$terms = wp_get_object_terms( $post->ID, 'wcs_extra_group', array( 'fields' => 'slugs' ) );
		$group = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';

		return array(
			'id'    => (int) $post->ID,
			'title' => $post->post_title,
			'slug'  => (string) ( $flat['_wcs_option_slug'] ?? sanitize_title( $post->post_title ) ),
			'group' => $group,
			'meta'  => $flat,
		);
	}

	/**
	 * Create or update an extra option.
	 *
	 * @param array<string, mixed> $data Option data.
	 * @return int|WP_Error
	 */
	public function save_option( array $data ) {
		$post_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		$postarr = array(
			'post_type'   => 'wcs_extra_option',
			'post_title'  => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'post_status' => 'publish',
		);

		if ( $post_id > 0 ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		if ( ! empty( $data['group'] ) ) {
			wp_set_object_terms( $post_id, sanitize_title( (string) $data['group'] ), 'wcs_extra_group' );
		}

		if ( ! empty( $data['slug'] ) ) {
			update_post_meta( $post_id, '_wcs_option_slug', sanitize_title( (string) $data['slug'] ) );
		}

		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $data['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( (string) $key ), $value );
			}
		}

		$this->cache->bump_version();
		return $post_id;
	}

	/**
	 * Delete an extra option.
	 *
	 * @param int $option_id Option post ID.
	 * @return bool|WP_Error
	 */
	public function delete_option( int $option_id ) {
		$result = wp_delete_post( $option_id, true );
		if ( $result ) {
			$this->cache->bump_version();
			return true;
		}
		return new WP_Error( 'wcs_delete_failed', __( 'Could not delete option.', 'woo-spiegelloft-configurator' ) );
	}
}
