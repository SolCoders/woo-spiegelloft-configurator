<?php
/**
 * Template admin meta boxes.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Template_Admin
 */
class WCS_Template_Admin {

	/**
	 * Template helper.
	 *
	 * @var WCS_Template
	 */
	private WCS_Template $template;

	/**
	 * Extras registry.
	 *
	 * @var WCS_Extras_Registry
	 */
	private WCS_Extras_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param WCS_Template          $template Template helper.
	 * @param WCS_Extras_Registry   $registry Extras registry.
	 */
	public function __construct( WCS_Template $template, WCS_Extras_Registry $registry ) {
		$this->template = $template;
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_wcs_template', array( $this, 'save_template' ), 10, 2 );
	}

	/**
	 * Register templates submenu.
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'wcs-configurator',
			__( 'Templates', 'woo-spiegelloft-configurator' ),
			__( 'Templates', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'edit.php?post_type=wcs_template'
		);
	}

	/**
	 * Register template meta boxes.
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wcs_template_groups',
			__( 'Enabled Extra Groups', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_groups_meta_box' ),
			'wcs_template',
			'normal',
			'high'
		);

		add_meta_box(
			'wcs_template_rules',
			__( 'Validation Rules', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_rules_meta_box' ),
			'wcs_template',
			'normal',
			'default'
		);
	}

	/**
	 * Render groups meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_groups_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wcs_save_template', 'wcs_template_nonce' );

		$data           = $this->template->get_template_data( (int) $post->ID ) ?? array( 'groups' => array(), 'rules' => array() );
		$enabled_groups = (array) ( $data['groups'] ?? array() );
		$all_groups     = $this->registry->get_groups();

		include WCS_PLUGIN_DIR . 'templates/admin/template-groups-meta.php';
	}

	/**
	 * Render rules meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_rules_meta_box( WP_Post $post ): void {
		$data  = $this->template->get_template_data( (int) $post->ID ) ?? array( 'rules' => array() );
		$rules = (array) ( $data['rules'] ?? array() );

		include WCS_PLUGIN_DIR . 'templates/admin/template-rules-meta.php';
	}

	/**
	 * Save template meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_template( int $post_id, WP_Post $post ): void {
		unset( $post );

		if ( ! isset( $_POST['wcs_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['wcs_template_nonce'] ) ), 'wcs_save_template' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$groups = isset( $_POST['wcs_template_groups'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['wcs_template_groups'] ) ) : array();

		$rules_raw = isset( $_POST['wcs_template_rules'] ) ? wp_unslash( (string) $_POST['wcs_template_rules'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rules     = json_decode( $rules_raw, true );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$this->template->save_template_data(
			$post_id,
			array(
				'groups' => $groups,
				'rules'  => $rules,
			)
		);
	}
}