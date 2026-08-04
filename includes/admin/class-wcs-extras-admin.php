<?php
/**
 * Extras catalog admin pages and AJAX.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extras_Admin
 */
class WCS_Extras_Admin {

	/**
	 * Extras catalog.
	 *
	 * @var WCS_Extras_Catalog
	 */
	private WCS_Extras_Catalog $catalog;

	/**
	 * Extras registry.
	 *
	 * @var WCS_Extras_Registry
	 */
	private WCS_Extras_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param WCS_Extras_Catalog  $catalog  Catalog instance.
	 * @param WCS_Extras_Registry $registry Registry instance.
	 */
	public function __construct( WCS_Extras_Catalog $catalog, WCS_Extras_Registry $registry ) {
		$this->catalog  = $catalog;
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_wcs_extra_option', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'wp_ajax_wcs_save_extra_option', array( $this, 'ajax_save_option' ) );
		add_action( 'wp_ajax_wcs_delete_extra_option', array( $this, 'ajax_delete_option' ) );
		add_action( 'wp_ajax_wcs_get_group_options', array( $this, 'ajax_get_group_options' ) );
	}

	/**
	 * Register extras submenu.
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'wcs-configurator',
			__( 'Extras Catalog', 'woo-spiegelloft-configurator' ),
			__( 'Extras', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-extras',
			array( $this, 'render_extras_page' )
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Add Extra Option', 'woo-spiegelloft-configurator' ),
			__( 'Add Extra', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'post-new.php?post_type=wcs_extra_option'
		);
	}

	/**
	 * Render extras catalog page.
	 */
	public function render_extras_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-spiegelloft-configurator' ) );
		}

		$groups  = $this->registry->get_groups();
		$options = array();

		foreach ( array_keys( $groups ) as $group_slug ) {
			$options[ $group_slug ] = $this->catalog->get_options_by_group( $group_slug );
		}

		include WCS_PLUGIN_DIR . 'templates/admin/extras-catalog.php';
	}

	/**
	 * Register meta boxes for extra options.
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wcs_extra_option_details',
			__( 'Option Details', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_option_meta_box' ),
			'wcs_extra_option',
			'normal',
			'high'
		);
	}

	/**
	 * Render option meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_option_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wcs_save_extra_option', 'wcs_extra_option_nonce' );

		$slug  = (string) get_post_meta( $post->ID, '_wcs_option_slug', true );
		$price = (string) get_post_meta( $post->ID, '_wcs_price', true );
		$image = (string) get_post_meta( $post->ID, '_wcs_image', true );
		$terms = wp_get_object_terms( $post->ID, 'wcs_extra_group', array( 'fields' => 'slugs' ) );
		$group = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
		$groups = $this->registry->get_groups();

		include WCS_PLUGIN_DIR . 'templates/admin/extra-option-meta.php';
	}

	/**
	 * Save option meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_boxes( int $post_id, WP_Post $post ): void {
		unset( $post );

		if ( ! isset( $_POST['wcs_extra_option_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['wcs_extra_option_nonce'] ) ), 'wcs_save_extra_option' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wcs_option_slug'] ) ) {
			update_post_meta( $post_id, '_wcs_option_slug', sanitize_title( wp_unslash( (string) $_POST['wcs_option_slug'] ) ) );
		}

		if ( isset( $_POST['wcs_price'] ) ) {
			update_post_meta( $post_id, '_wcs_price', wc_format_decimal( wp_unslash( (string) $_POST['wcs_price'] ) ) );
		}

		if ( isset( $_POST['wcs_image'] ) ) {
			update_post_meta( $post_id, '_wcs_image', esc_url_raw( wp_unslash( (string) $_POST['wcs_image'] ) ) );
		}

		if ( isset( $_POST['wcs_extra_group'] ) ) {
			wp_set_object_terms( $post_id, sanitize_title( wp_unslash( (string) $_POST['wcs_extra_group'] ) ), 'wcs_extra_group' );
		}
	}

	/**
	 * AJAX: save extra option.
	 */
	public function ajax_save_option(): void {
		check_ajax_referer( 'wcs_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$data = isset( $_POST['option'] ) ? wp_unslash( $_POST['option'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'woo-spiegelloft-configurator' ) ) );
		}

		$payload = array(
			'id'    => isset( $data['id'] ) ? absint( $data['id'] ) : 0,
			'title' => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'slug'  => sanitize_title( (string) ( $data['slug'] ?? '' ) ),
			'group' => sanitize_title( (string) ( $data['group'] ?? '' ) ),
			'meta'  => array(
				'_wcs_price' => wc_format_decimal( (string) ( $data['price'] ?? '0' ) ),
				'_wcs_image' => esc_url_raw( (string) ( $data['image'] ?? '' ) ),
			),
		);

		$result = $this->catalog->save_option( $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'id' => $result ) );
	}

	/**
	 * AJAX: delete extra option.
	 */
	public function ajax_delete_option(): void {
		check_ajax_referer( 'wcs_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$option_id = isset( $_POST['option_id'] ) ? absint( $_POST['option_id'] ) : 0;
		$result    = $this->catalog->delete_option( $option_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: get options for a group.
	 */
	public function ajax_get_group_options(): void {
		check_ajax_referer( 'wcs_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$group = isset( $_POST['group'] ) ? sanitize_title( wp_unslash( (string) $_POST['group'] ) ) : '';
		$options = $this->catalog->get_options_by_group( $group );

		wp_send_json_success( array( 'options' => $options ) );
	}
}