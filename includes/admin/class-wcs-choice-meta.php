<?php
/**
 * Choice meta boxes for wcs_extra_option posts.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Choice_Meta
 */
class WCS_Choice_Meta {

	/**
	 * Extras registry.
	 *
	 * @var WCS_Extras_Registry
	 */
	private WCS_Extras_Registry $registry;

	/**
	 * Field type registry.
	 *
	 * @var WCS_Extra_Field_Type_Registry
	 */
	private WCS_Extra_Field_Type_Registry $field_registry;

	/**
	 * Constructor.
	 *
	 * @param WCS_Extras_Registry           $registry       Extras registry.
	 * @param WCS_Extra_Field_Type_Registry $field_registry Field registry.
	 */
	public function __construct( WCS_Extras_Registry $registry, WCS_Extra_Field_Type_Registry $field_registry ) {
		$this->registry       = $registry;
		$this->field_registry = $field_registry;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_wcs_extra_option', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register meta boxes.
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wcs_choice_details',
			__( 'Choice details', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_details_meta_box' ),
			'wcs_extra_option',
			'normal',
			'high'
		);

		add_meta_box(
			'wcs_choice_nested',
			__( 'Customer follow-up choices', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_nested_meta_box' ),
			'wcs_extra_option',
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue choice editor assets.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'wcs_extra_option' !== ( $screen->post_type ?? '' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'wcs-admin-choice-editor',
			WCS_PLUGIN_URL . 'assets/js/admin-choice-editor.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WCS_VERSION,
			true
		);

		$presets_file = WCS_PLUGIN_DIR . 'includes/data/position-presets.php';
		$presets      = file_exists( $presets_file ) ? include $presets_file : array();

		wp_localize_script(
			'wcs-admin-choice-editor',
			'wcsChoiceEditor',
			array(
				'groups'  => $this->registry->get_groups(),
				'presets' => is_array( $presets ) ? $presets : array(),
				'i18n'    => array(
					'addRow'       => __( 'Add row', 'woo-spiegelloft-configurator' ),
					'removeRow'    => __( 'Remove', 'woo-spiegelloft-configurator' ),
					'usePreset'    => __( 'Use preset', 'woo-spiegelloft-configurator' ),
					'selectImage'  => __( 'Select image', 'woo-spiegelloft-configurator' ),
					'noThanks'     => __( 'Add "No thanks" option', 'woo-spiegelloft-configurator' ),
					'noNested'     => __( 'Select a category above to configure follow-up choices.', 'woo-spiegelloft-configurator' ),
				),
			)
		);
	}

	/**
	 * Render choice details meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_details_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wcs_save_choice', 'wcs_choice_nonce' );

		$option_data = $this->get_option_data( (int) $post->ID );
		$terms       = wp_get_object_terms( $post->ID, 'wcs_extra_group', array( 'fields' => 'slugs' ) );
		$group       = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
		if ( '' === $group && isset( $_GET['wcs_extra_group'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$group = sanitize_title( wp_unslash( (string) $_GET['wcs_extra_group'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$groups      = $this->registry->get_groups();
		$legacy_id   = (int) get_post_meta( $post->ID, '_wcs_legacy_id', true );
		$slug        = (string) get_post_meta( $post->ID, '_wcs_option_slug', true );
		$price       = (string) ( $option_data['price'] ?? get_post_meta( $post->ID, '_wcs_price', true ) );
		$image       = (string) ( $option_data['image'] ?? get_post_meta( $post->ID, '_wcs_image', true ) );
		$value       = (string) ( $option_data['value'] ?? $slug );
		$position_enabled = ! empty( $option_data['position_enabled'] );
		$position_label   = (string) ( $option_data['position_label'] ?? '' );
		$position_options = isset( $option_data['position_options'] ) && is_array( $option_data['position_options'] )
			? $option_data['position_options']
			: array();

		include WCS_PLUGIN_DIR . 'templates/admin/choice-details-meta.php';
	}

	/**
	 * Render nested choices meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_nested_meta_box( WP_Post $post ): void {
		$option_data = $this->get_option_data( (int) $post->ID );
		$terms       = wp_get_object_terms( $post->ID, 'wcs_extra_group', array( 'fields' => 'slugs' ) );
		$group       = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
		$groups      = $this->registry->get_groups();
		$nested          = $this->extract_nested_data( $option_data );
		$field_registry  = $this->field_registry;

		include WCS_PLUGIN_DIR . 'templates/admin/choice-nested-meta.php';
	}

	/**
	 * Save choice meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_boxes( int $post_id, WP_Post $post ): void {
		unset( $post );

		if ( ! isset( $_POST['wcs_choice_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['wcs_choice_nonce'] ) ), 'wcs_save_choice' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$group_slug = isset( $_POST['wcs_extra_group'] ) ? sanitize_title( wp_unslash( (string) $_POST['wcs_extra_group'] ) ) : '';
		if ( $group_slug ) {
			wp_set_object_terms( $post_id, $group_slug, 'wcs_extra_group' );
		}

		$name  = isset( $_POST['wcs_option_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wcs_option_name'] ) ) : get_the_title( $post_id );
		$value = isset( $_POST['wcs_option_value'] ) ? sanitize_title( wp_unslash( (string) $_POST['wcs_option_value'] ) ) : '';
		$price = isset( $_POST['wcs_price'] ) ? wc_format_decimal( wp_unslash( (string) $_POST['wcs_price'] ) ) : '0';
		$image = isset( $_POST['wcs_image'] ) ? esc_url_raw( wp_unslash( (string) $_POST['wcs_image'] ) ) : '';

		if ( $name && $name !== get_the_title( $post_id ) ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
				)
			);
		}

		$legacy_id = isset( $_POST['wcs_legacy_id'] ) ? absint( $_POST['wcs_legacy_id'] ) : 0;

		$option_data = array(
			'name'  => $name,
			'value' => $value,
			'price' => (float) $price,
			'image' => $image,
		);

		$position_enabled = ! empty( $_POST['wcs_position_enabled'] );
		$position_label   = isset( $_POST['wcs_position_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wcs_position_label'] ) ) : '';
		$position_raw     = isset( $_POST['wcs_position_options'] ) ? wp_unslash( $_POST['wcs_position_options'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$positions        = array();
		if ( $position_enabled && is_array( $position_raw ) ) {
			foreach ( $position_raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$value_slug = sanitize_title( (string) ( $row['value'] ?? $label ) );
				if ( '' === $label && '' === $value_slug ) {
					continue;
				}
				$positions[] = array(
					'label' => $label ?: $value_slug,
					'value' => $value_slug,
				);
			}
		}
		if ( $position_enabled && ! empty( $positions ) ) {
			$option_data['position_enabled'] = true;
			$option_data['position_label']   = $position_label ?: sprintf(
				/* translators: %s: choice label */
				__( 'Position of the %s', 'woo-spiegelloft-configurator' ),
				$name
			);
			$option_data['position_options'] = $positions;
		}

		$group_def = $group_slug ? $this->registry->get_group( $group_slug ) : null;
		if ( $group_def && ! empty( $group_def['optional_fields'] ) && isset( $_POST['wcs_nested'] ) && is_array( $_POST['wcs_nested'] ) ) {
			$raw_nested = wp_unslash( $_POST['wcs_nested'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( (array) $group_def['optional_fields'] as $field_key => $field_def ) {
				if ( ! is_array( $field_def ) ) {
					continue;
				}
				$field_def['id'] = (string) $field_key;
				if ( isset( $raw_nested[ $field_key ] ) ) {
					$option_data[ $field_key ] = $this->field_registry->sanitize_value( $raw_nested[ $field_key ], $field_def );
				}
			}
		}

		update_post_meta( $post_id, '_wcs_option_data', $option_data );
		update_post_meta( $post_id, '_wcs_option_slug', $value );
		update_post_meta( $post_id, '_wcs_price', $price );
		update_post_meta( $post_id, '_wcs_image', $image );
		update_post_meta( $post_id, '_wcs_legacy_id', $legacy_id );
	}

	/**
	 * Get stored option data array.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_option_data( int $post_id ): array {
		$data = get_post_meta( $post_id, '_wcs_option_data', true );
		if ( is_array( $data ) ) {
			return $data;
		}

		return array(
			'name'  => get_the_title( $post_id ),
			'value' => (string) get_post_meta( $post_id, '_wcs_option_slug', true ),
			'price' => (float) get_post_meta( $post_id, '_wcs_price', true ),
			'image' => (string) get_post_meta( $post_id, '_wcs_image', true ),
		);
	}

	/**
	 * Extract nested field values from option data.
	 *
	 * @param array<string, mixed> $option_data Option data.
	 * @return array<string, mixed>
	 */
	private function extract_nested_data( array $option_data ): array {
		$base_keys = array( 'name', 'value', 'price', 'image' );
		$nested    = array();

		foreach ( $option_data as $key => $value ) {
			if ( ! in_array( $key, $base_keys, true ) ) {
				$nested[ $key ] = $value;
			}
		}

		return $nested;
	}
}
