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

		if ( false === get_option( 'wcs_template_seed_version' ) ) {
			self::seed_default_templates();
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
					'name'          => __( 'Customization choices', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Customization choice', 'woo-spiegelloft-configurator' ),
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
					'name'          => __( 'Mirror templates', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Mirror template', 'woo-spiegelloft-configurator' ),
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
					'name'          => __( 'Choice categories', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Choice category', 'woo-spiegelloft-configurator' ),
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

		/** @var array<int, array<string, mixed>> $seed */
		$seed = include $seed_file;
		if ( empty( $seed ) || ! is_array( $seed ) ) {
			return;
		}

		$term_cache = array();
		$batch      = array();

		foreach ( $seed as $option ) {
			if ( ! is_array( $option ) || empty( $option['title'] ) || empty( $option['group'] ) ) {
				continue;
			}

			$group_slug = sanitize_title( (string) $option['group'] );

			if ( ! isset( $term_cache[ $group_slug ] ) ) {
				$term = term_exists( $group_slug, 'wcs_extra_group' );
				if ( ! $term ) {
					$term = wp_insert_term( $group_slug, 'wcs_extra_group', array( 'slug' => $group_slug ) );
				}
				$term_id = is_array( $term ) ? (int) ( $term['term_id'] ?? 0 ) : (int) $term;
				$term_cache[ $group_slug ] = $term_id;
			}

			$term_id = (int) $term_cache[ $group_slug ];
			if ( $term_id <= 0 ) {
				continue;
			}

			$legacy_id = (int) ( $option['legacy_id'] ?? 0 );
			$value     = sanitize_title( (string) ( $option['value'] ?? '' ) );

			$existing = get_posts(
				array(
					'post_type'      => 'wcs_extra_option',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'   => '_wcs_option_slug',
							'value' => $value,
						),
					),
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'wcs_extra_group',
							'field'    => 'term_id',
							'terms'    => $term_id,
						),
					),
				)
			);

			if ( ! empty( $existing ) ) {
				continue;
			}

			$option_data = array(
				'name'  => sanitize_text_field( (string) $option['title'] ),
				'value' => $value,
				'price' => (float) ( $option['price'] ?? 0 ),
				'image' => esc_url_raw( (string) ( $option['image'] ?? '' ) ),
			);

			if ( ! empty( $option['nested'] ) && is_array( $option['nested'] ) ) {
				foreach ( $option['nested'] as $nested_key => $nested_value ) {
					$option_data[ sanitize_key( (string) $nested_key ) ] = $nested_value;
				}
			}

			$batch[] = array(
				'title'       => sanitize_text_field( (string) $option['title'] ),
				'term_id'     => $term_id,
				'value'       => $value,
				'legacy_id'   => $legacy_id,
				'price'       => wc_format_decimal( (string) ( $option['price'] ?? 0 ) ),
				'image'       => esc_url_raw( (string) ( $option['image'] ?? '' ) ),
				'option_data' => $option_data,
			);
		}

		foreach ( $batch as $item ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wcs_extra_option',
					'post_title'  => $item['title'],
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			wp_set_object_terms( (int) $post_id, array( (int) $item['term_id'] ), 'wcs_extra_group' );
			update_post_meta( (int) $post_id, '_wcs_option_data', $item['option_data'] );
			update_post_meta( (int) $post_id, '_wcs_option_slug', $item['value'] );
			update_post_meta( (int) $post_id, '_wcs_legacy_id', (int) $item['legacy_id'] );
			update_post_meta( (int) $post_id, '_wcs_price', $item['price'] );
			update_post_meta( (int) $post_id, '_wcs_image', $item['image'] );
		}

		update_option( 'wcs_seed_version', WCS_VERSION );
	}

	/**
	 * Seed default mirror templates with editable restriction rules.
	 */
	private static function seed_default_templates(): void {
		$seed_file = WCS_PLUGIN_DIR . 'includes/data/seed-templates.php';
		if ( ! file_exists( $seed_file ) ) {
			return;
		}

		/** @var array<int, array<string, mixed>> $templates */
		$templates = include $seed_file;
		if ( empty( $templates ) || ! is_array( $templates ) ) {
			return;
		}

		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) || empty( $template['title'] ) || empty( $template['slug'] ) ) {
				continue;
			}

			$seed_slug = sanitize_title( (string) $template['slug'] );
			$existing  = get_posts(
				array(
					'post_type'      => 'wcs_template',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_wcs_template_seed_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $seed_slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);

			if ( ! empty( $existing ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wcs_template',
					'post_title'  => sanitize_text_field( (string) $template['title'] ),
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			unset( $template['title'] );
			update_post_meta( (int) $post_id, '_wcs_template_data', $template );
			update_post_meta( (int) $post_id, '_wcs_template_seed_slug', $seed_slug );
		}

		update_option( 'wcs_template_seed_version', WCS_VERSION );
	}
}
