<?php
/**
 * Configurator template CPT helpers.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Template
 */
class WCS_Template {

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
		add_action( 'save_post_wcs_template', array( $this, 'invalidate_cache' ), 10, 1 );
	}

	/**
	 * Register wcs_template CPT.
	 */
	public function register_post_type(): void {
		register_post_type(
			'wcs_template',
			array(
				'labels'              => array(
					'name'          => __( 'Mirror templates', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Mirror template', 'woo-spiegelloft-configurator' ),
					'add_new_item'  => __( 'Add mirror template', 'woo-spiegelloft-configurator' ),
					'edit_item'     => __( 'Edit mirror template', 'woo-spiegelloft-configurator' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}

	/**
	 * Invalidate template cache.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( int $post_id ): void {
		unset( $post_id );
		$this->cache->bump_version();
	}

	/**
	 * Get template data array.
	 *
	 * @param int $template_id Template post ID.
	 * @return array<string, mixed>|null
	 */
	public function get_template_data( int $template_id ): ?array {
		$cache_key = 'template_' . $template_id;
		$cached    = $this->cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$post = get_post( $template_id );
		if ( ! $post || 'wcs_template' !== $post->post_type ) {
			return null;
		}

		$data = get_post_meta( $template_id, '_wcs_template_data', true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$dimensions = (array) ( $data['dimensions'] ?? array() );
		$side_measurements = $this->normalize_side_measurements( $dimensions );

		$formatted = array(
			'id'                => $template_id,
			'title'             => $post->post_title,
			'panel_template'    => (string) ( $data['panel_template'] ?? 'bathroomMirror' ),
			'slug'              => (string) ( $data['slug'] ?? sanitize_title( $post->post_title ) ),
			'type'              => (string) ( $data['type'] ?? '' ),
			'dimensions'        => array(
				'measurement_mode'  => (string) ( $dimensions['measurement_mode'] ?? 'standard' ),
				'min_width'         => (int) ( $dimensions['min_width'] ?? 400 ),
				'max_width'         => (int) ( $dimensions['max_width'] ?? 2500 ),
				'min_height'        => (int) ( $dimensions['min_height'] ?? 400 ),
				'max_height'        => (int) ( $dimensions['max_height'] ?? 2500 ),
				'top_min_width'     => (int) ( $dimensions['top_min_width'] ?? $dimensions['min_width'] ?? 0 ),
				'top_max_width'     => (int) ( $dimensions['top_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
				'bottom_min_width'  => (int) ( $dimensions['bottom_min_width'] ?? $dimensions['min_width'] ?? 0 ),
				'bottom_max_width'  => (int) ( $dimensions['bottom_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
				'left_min_height'   => (int) ( $dimensions['left_min_height'] ?? $dimensions['min_height'] ?? 0 ),
				'left_max_height'   => (int) ( $dimensions['left_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
				'right_min_height'  => (int) ( $dimensions['right_min_height'] ?? $dimensions['min_height'] ?? 0 ),
				'right_max_height'  => (int) ( $dimensions['right_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
				'side_measurements' => $side_measurements,
			),
			'enabled_groups'    => (array) ( $data['enabled_groups'] ?? $data['groups'] ?? array() ),
			'group_order'       => (array) ( $data['group_order'] ?? array() ),
			'step_map'          => (array) ( $data['step_map'] ?? array() ),
			'choice_step_map'   => (array) ( $data['choice_step_map'] ?? array() ),
			'extra_option_map'  => (array) ( $data['extra_option_map'] ?? array() ),
			'validation_rules'  => (array) ( $data['validation_rules'] ?? $data['rules'] ?? array() ),
			'behavior_rules'    => (array) ( $data['behavior_rules'] ?? $data['validation_rules'] ?? $data['rules'] ?? array() ),
			'edge_override'     => (array) ( $data['edge_override'] ?? array() ),
			'groups'            => (array) ( $data['enabled_groups'] ?? $data['groups'] ?? array() ),
			'rules'             => (array) ( $data['validation_rules'] ?? $data['rules'] ?? array() ),
			'meta'              => (array) ( $data['meta'] ?? array() ),
		);

		$this->cache->set( $cache_key, $formatted );
		return $formatted;
	}

	/**
	 * Save template data.
	 *
	 * @param int                  $template_id Template post ID.
	 * @param array<string, mixed> $data        Template data.
	 * @return bool
	 */
	public function save_template_data( int $template_id, array $data ): bool {
		$dimensions = (array) ( $data['dimensions'] ?? array() );
		$side_measurements = $this->normalize_side_measurements( $dimensions );

		$sanitized = array(
			'panel_template'   => sanitize_text_field( (string) ( $data['panel_template'] ?? 'bathroomMirror' ) ),
			'slug'             => sanitize_title( (string) ( $data['slug'] ?? '' ) ),
			'type'             => sanitize_text_field( (string) ( $data['type'] ?? '' ) ),
			'dimensions'       => array(
				'measurement_mode'  => 'sidewise' === (string) ( $dimensions['measurement_mode'] ?? 'standard' ) ? 'sidewise' : 'standard',
				'min_width'         => absint( $dimensions['min_width'] ?? 400 ),
				'max_width'         => absint( $dimensions['max_width'] ?? 2500 ),
				'min_height'        => absint( $dimensions['min_height'] ?? 400 ),
				'max_height'        => absint( $dimensions['max_height'] ?? 2500 ),
				'top_min_width'     => absint( $dimensions['top_min_width'] ?? $dimensions['min_width'] ?? 0 ),
				'top_max_width'     => absint( $dimensions['top_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
				'bottom_min_width'  => absint( $dimensions['bottom_min_width'] ?? $dimensions['min_width'] ?? 0 ),
				'bottom_max_width'  => absint( $dimensions['bottom_max_width'] ?? $dimensions['max_width'] ?? 2000 ),
				'left_min_height'   => absint( $dimensions['left_min_height'] ?? $dimensions['min_height'] ?? 0 ),
				'left_max_height'   => absint( $dimensions['left_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
				'right_min_height'  => absint( $dimensions['right_min_height'] ?? $dimensions['min_height'] ?? 0 ),
				'right_max_height'  => absint( $dimensions['right_max_height'] ?? $dimensions['max_height'] ?? 2000 ),
				'side_measurements' => $side_measurements,
			),
			'enabled_groups'   => array_values( array_map( 'sanitize_text_field', (array) ( $data['enabled_groups'] ?? array() ) ) ),
			'group_order'      => array_values( array_map( 'sanitize_text_field', (array) ( $data['group_order'] ?? array() ) ) ),
			'step_map'         => is_array( $data['step_map'] ?? null ) ? array_map( static fn( $step ): int => max( 1, absint( $step ) ), $data['step_map'] ) : array(),
			'choice_step_map'  => is_array( $data['choice_step_map'] ?? null ) ? array_map( static fn( $step ): int => max( 1, absint( $step ) ), $data['choice_step_map'] ) : array(),
			'extra_option_map' => is_array( $data['extra_option_map'] ?? null ) ? $data['extra_option_map'] : array(),
			'validation_rules' => is_array( $data['validation_rules'] ?? null ) ? $data['validation_rules'] : array(),
			'behavior_rules'   => is_array( $data['behavior_rules'] ?? null ) ? $data['behavior_rules'] : array(),
			'edge_override'    => is_array( $data['edge_override'] ?? null ) ? $data['edge_override'] : array(),
		);

		update_post_meta( $template_id, '_wcs_template_data', $sanitized );
		$this->cache->delete( 'template_' . $template_id );
		return true;
	}

	/**
	 * Normalize repeatable side measurements.
	 *
	 * @param array<string, mixed> $dimensions Dimension settings.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_side_measurements( array $dimensions ): array {
		$sides = array();
		if ( ! empty( $dimensions['side_measurements'] ) && is_array( $dimensions['side_measurements'] ) ) {
			foreach ( $dimensions['side_measurements'] as $side ) {
				if ( ! is_array( $side ) ) {
					continue;
				}
				$label = sanitize_text_field( (string) ( $side['label'] ?? '' ) );
				$key   = sanitize_title( (string) ( $side['key'] ?? $label ) );
				if ( '' === $label || '' === $key ) {
					continue;
				}
				$sides[] = array(
					'label' => $label,
					'key'   => $key,
					'min'   => absint( $side['min'] ?? $side['min_width'] ?? $side['min_height'] ?? 400 ),
					'max'   => absint( $side['max'] ?? $side['max_width'] ?? $side['max_height'] ?? 2500 ),
				);
			}
		}

		if ( ! empty( $sides ) ) {
			return array_values( $sides );
		}

		return array(
			array(
				'label' => __( 'Width', 'woo-spiegelloft-configurator' ),
				'key'   => 'width',
				'min'   => absint( $dimensions['min_width'] ?? 400 ),
				'max'   => absint( $dimensions['max_width'] ?? 2500 ),
			),
			array(
				'label' => __( 'Height', 'woo-spiegelloft-configurator' ),
				'key'   => 'height',
				'min'   => absint( $dimensions['min_height'] ?? 400 ),
				'max'   => absint( $dimensions['max_height'] ?? 2500 ),
			),
		);
	}

	/**
	 * Get template ID assigned to a product.
	 *
	 * @param int $product_id Product ID.
	 */
	public function get_product_template_id( int $product_id ): int {
		return absint( get_post_meta( $product_id, '_wcs_template_id', true ) );
	}

	/**
	 * Assign template to product.
	 *
	 * @param int $product_id  Product ID.
	 * @param int $template_id Template ID.
	 */
	public function set_product_template_id( int $product_id, int $template_id ): void {
		update_post_meta( $product_id, '_wcs_template_id', $template_id );
	}

	/**
	 * List all templates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_templates(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wcs_template',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$list = array();
		foreach ( $posts as $post ) {
			$list[] = array(
				'id'    => (int) $post->ID,
				'title' => $post->post_title,
			);
		}
		return $list;
	}
}
