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

		add_meta_box(
			'wcs_choice_customer_fields',
			__( 'Customer fields', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_customer_fields_meta_box' ),
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

		include WCS_PLUGIN_DIR . 'templates/admin/choice-details-meta.php';
	}

	/**
	 * Render customer fields meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_customer_fields_meta_box( WP_Post $post ): void {
		$option_data     = $this->get_option_data( (int) $post->ID );
		$customer_fields = $this->get_customer_fields_for_admin( $option_data );

		include WCS_PLUGIN_DIR . 'templates/admin/choice-customer-fields-meta.php';
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

		$customer_fields_raw = isset( $_POST['wcs_customer_fields'] ) ? wp_unslash( $_POST['wcs_customer_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$customer_fields     = $this->sanitize_customer_fields( $customer_fields_raw );
		if ( ! empty( $customer_fields ) ) {
			$option_data['customer_fields'] = $customer_fields;
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
	 * Return new customer fields, or adapt legacy per-choice position settings.
	 *
	 * @param array<string, mixed> $option_data Option data.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_customer_fields_for_admin( array $option_data ): array {
		if ( ! empty( $option_data['customer_fields'] ) && is_array( $option_data['customer_fields'] ) ) {
			return array_values( $option_data['customer_fields'] );
		}

		if ( empty( $option_data['position_enabled'] ) || empty( $option_data['position_options'] ) || ! is_array( $option_data['position_options'] ) ) {
			return array();
		}

		return array(
			array(
				'label'       => (string) ( $option_data['position_label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ),
				'key'         => 'position',
				'type'        => 'dropdown',
				'required'    => false,
				'price_enabled' => false,
				'placeholder' => '',
				'options'     => array_values( $option_data['position_options'] ),
			),
		);
	}

	/**
	 * Sanitize merchant-defined conditional customer fields.
	 *
	 * @param mixed $raw Raw field rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_customer_fields( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$fields = array();
		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $field['label'] ?? '' ) );
			$key   = sanitize_title( (string) ( $field['key'] ?? $label ) );
			$type  = 'text' === (string) ( $field['type'] ?? 'dropdown' ) ? 'text' : 'dropdown';
			if ( '' === $label && '' === $key ) {
				continue;
			}

			$row = array(
				'label'       => $label ?: ucwords( str_replace( '-', ' ', $key ) ),
				'key'         => $key,
				'type'        => $type,
				'required'    => ! empty( $field['required'] ),
				'placeholder' => sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
				'price_enabled' => ! empty( $field['price_enabled'] ),
			);

			if ( 'dropdown' === $type ) {
				$row['options']       = $this->sanitize_customer_field_options( $field['options'] ?? array(), ! empty( $row['price_enabled'] ) );
				if ( empty( $row['options'] ) ) {
					continue;
				}
			} elseif ( ! empty( $row['price_enabled'] ) ) {
				$row['price'] = (float) wc_format_decimal( (string) ( $field['price'] ?? '0' ) );
			}

			$fields[] = $row;
		}

		return $fields;
	}

	/**
	 * Sanitize dropdown values for a customer field.
	 *
	 * @param mixed $raw           Raw option rows.
	 * @param bool  $price_enabled Whether row prices are active.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_customer_field_options( $raw, bool $price_enabled ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$options = array();
		foreach ( $raw as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $option['label'] ?? '' ) );
			$value = sanitize_title( (string) ( $option['value'] ?? $label ) );
			if ( '' === $label && '' === $value ) {
				continue;
			}

			$options[] = array(
				'label' => $label ?: $value,
				'value' => $value,
				'price' => $price_enabled ? (float) wc_format_decimal( (string) ( $option['price'] ?? '0' ) ) : 0,
			) + $this->sanitize_nested_customer_fields_for_option( $option );
		}

		return $options;
	}

	/**
	 * Sanitize optional nested customer fields for one dropdown value.
	 *
	 * @param array<string, mixed> $option Raw dropdown option.
	 * @return array<string, mixed>
	 */
	private function sanitize_nested_customer_fields_for_option( array $option ): array {
		if ( empty( $option['nested_enabled'] ) && empty( $option['position_enabled'] ) ) {
			return array();
		}

		$fields = $this->sanitize_customer_fields( $option['customer_fields'] ?? array() );
		if ( empty( $fields ) && ! empty( $option['position_options'] ) ) {
			$fields = $this->legacy_position_to_customer_fields( $option );
		}

		if ( empty( $fields ) ) {
			return array();
		}

		return array(
			'nested_enabled' => true,
			'customer_fields' => $fields,
		);
	}

	/**
	 * Convert legacy per-value position rows into one nested dropdown field.
	 *
	 * @param array<string, mixed> $option Raw dropdown option.
	 * @return array<int, array<string, mixed>>
	 */
	private function legacy_position_to_customer_fields( array $option ): array {
		return array(
			array(
				'label'         => sanitize_text_field( (string) ( $option['position_label'] ?? __( 'Position', 'woo-spiegelloft-configurator' ) ) ),
				'key'           => 'position',
				'type'          => 'dropdown',
				'required'      => true,
				'placeholder'   => '',
				'price_enabled' => false,
				'options'       => $this->sanitize_customer_field_options( $option['position_options'] ?? array(), false ),
			),
		);
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
