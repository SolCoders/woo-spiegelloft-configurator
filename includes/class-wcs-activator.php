<?php
/**
 * Plugin activation routines.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Activator
 */
class WCS_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::register_post_types();
		self::register_taxonomies();
		flush_rewrite_rules();

		if ( false === get_option( 'wcs_seed_version' ) ) {
			self::seed_default_options();
		}

		update_option( 'wcs_cache_version', (string) time() );
	}

	/**
	 * Register CPTs needed during activation.
	 */
	private static function register_post_types(): void {
		register_post_type(
			'wcs_extra_option',
			array(
				'labels'       => array(
					'name'          => __( 'Extra Options', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Extra Option', 'woo-spiegelloft-configurator' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'rewrite'      => false,
			)
		);

		register_post_type(
			'wcs_template',
			array(
				'labels'       => array(
					'name'          => __( 'Configurator Templates', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Configurator Template', 'woo-spiegelloft-configurator' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'rewrite'      => false,
			)
		);
	}

	/**
	 * Register taxonomies needed during activation.
	 */
	private static function register_taxonomies(): void {
		register_taxonomy(
			'wcs_extra_group',
			'wcs_extra_option',
			array(
				'labels'       => array(
					'name'          => __( 'Extra Groups', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Extra Group', 'woo-spiegelloft-configurator' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'hierarchical' => false,
				'rewrite'      => false,
			)
		);
	}

	/**
	 * Seed default options from data file.
	 */
	private static function seed_default_options(): void {
		$seed_file = WCS_PLUGIN_DIR . 'includes/data/seed-options.php';
		if ( ! file_exists( $seed_file ) ) {
			return;
		}

		/** @var array<string, mixed> $seed */
		$seed = include $seed_file;
		if ( empty( $seed ) || ! is_array( $seed ) ) {
			return;
		}

		foreach ( $seed as $group_slug => $options ) {
			if ( ! is_array( $options ) ) {
				continue;
			}

			$term = term_exists( $group_slug, 'wcs_extra_group' );
			if ( ! $term ) {
				$term = wp_insert_term( $group_slug, 'wcs_extra_group', array( 'slug' => $group_slug ) );
			}
			$term_id = is_array( $term ) ? (int) ( $term['term_id'] ?? 0 ) : (int) $term;
			if ( $term_id <= 0 ) {
				continue;
			}

			foreach ( $options as $option ) {
				if ( ! is_array( $option ) || empty( $option['title'] ) ) {
					continue;
				}

				$existing = get_posts(
					array(
						'post_type'      => 'wcs_extra_option',
						'post_status'    => 'publish',
						'title'          => $option['title'],
						'posts_per_page' => 1,
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'wcs_extra_group',
								'field'     => 'term_id',
								'terms'     => $term_id,
							),
						),
						'fields'         => 'ids',
					)
				);

				if ( ! empty( $existing ) ) {
					continue;
				}

				$post_id = wp_insert_post(
					array(
						'post_type'   => 'wcs_extra_option',
						'post_title'  => sanitize_text_field( (string) $option['title'] ),
						'post_status' => 'publish',
					),
					true
				);

				if ( is_wp_error( $post_id ) || ! $post_id ) {
					continue;
				}

				wp_set_object_terms( (int) $post_id, array( $term_id ), 'wcs_extra_group' );

				if ( isset( $option['meta'] ) && is_array( $option['meta'] ) ) {
					foreach ( $option['meta'] as $meta_key => $meta_value ) {
						update_post_meta( (int) $post_id, sanitize_key( (string) $meta_key ), $meta_value );
					}
				}

				if ( isset( $option['slug'] ) ) {
					update_post_meta( (int) $post_id, '_wcs_option_slug', sanitize_title( (string) $option['slug'] ) );
				}
			}
		}

		update_option( 'wcs_seed_version', WCS_VERSION );
	}
}