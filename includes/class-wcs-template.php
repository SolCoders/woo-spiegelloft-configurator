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
					'name'          => __( 'Configurator Templates', 'woo-spiegelloft-configurator' ),
					'singular_name' => __( 'Configurator Template', 'woo-spiegelloft-configurator' ),
					'add_new_item'  => __( 'Add Template', 'woo-spiegelloft-configurator' ),
					'edit_item'     => __( 'Edit Template', 'woo-spiegelloft-configurator' ),
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

		$formatted = array(
			'id'     => $template_id,
			'title'  => $post->post_title,
			'groups' => (array) ( $data['groups'] ?? array() ),
			'rules'  => (array) ( $data['rules'] ?? array() ),
			'meta'   => (array) ( $data['meta'] ?? array() ),
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
		$sanitized = array(
			'groups' => array_values( array_map( 'sanitize_text_field', (array) ( $data['groups'] ?? array() ) ) ),
			'rules'  => is_array( $data['rules'] ?? null ) ? $data['rules'] : array(),
			'meta'   => is_array( $data['meta'] ?? null ) ? $data['meta'] : array(),
		);

		update_post_meta( $template_id, '_wcs_template_data', $sanitized );
		$this->cache->delete( 'template_' . $template_id );
		return true;
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