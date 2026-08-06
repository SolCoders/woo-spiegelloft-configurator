<?php
/**
 * Extras catalog admin: list table enhancements and submenu.
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
		add_action( 'init', array( $this, 'register_post_type_labels' ), 15 );
		add_filter( 'manage_wcs_extra_option_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_wcs_extra_option_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'taxonomy_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_taxonomy' ) );
		add_action( 'wp_ajax_wcs_get_group_options', array( $this, 'ajax_get_group_options' ) );
		add_action( 'wp_ajax_wcs_delete_choice', array( $this, 'ajax_delete_choice' ) );
		add_action( 'wp_ajax_wcs_save_choice_positions', array( $this, 'ajax_save_choice_positions' ) );
		add_action( 'wp_ajax_wcs_save_group_positions', array( $this, 'ajax_save_group_positions' ) );
	}

	/**
	 * Update CPT labels to plain language.
	 */
	public function register_post_type_labels(): void {
		global $wp_post_types;

		if ( ! isset( $wp_post_types['wcs_extra_option'] ) ) {
			return;
		}

		$labels = &$wp_post_types['wcs_extra_option']->labels;
		$labels->name               = __( 'Customization choices', 'woo-spiegelloft-configurator' );
		$labels->singular_name      = __( 'Customization choice', 'woo-spiegelloft-configurator' );
		$labels->add_new            = __( 'Add choice', 'woo-spiegelloft-configurator' );
		$labels->add_new_item       = __( 'Add customization choice', 'woo-spiegelloft-configurator' );
		$labels->edit_item          = __( 'Edit customization choice', 'woo-spiegelloft-configurator' );
		$labels->new_item           = __( 'New customization choice', 'woo-spiegelloft-configurator' );
		$labels->view_item          = __( 'View customization choice', 'woo-spiegelloft-configurator' );
		$labels->search_items       = __( 'Search choices', 'woo-spiegelloft-configurator' );
		$labels->not_found          = __( 'No choices found.', 'woo-spiegelloft-configurator' );
		$labels->not_found_in_trash = __( 'No choices found in trash.', 'woo-spiegelloft-configurator' );
		$labels->all_items          = __( 'All choices', 'woo-spiegelloft-configurator' );
	}

	/**
	 * Register customization choices submenu.
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'wcs-configurator',
			__( 'Customization choices', 'woo-spiegelloft-configurator' ),
			__( 'Customization choices', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'wcs-choices',
			array( $this, 'render_choices_page' )
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Add customization choice', 'woo-spiegelloft-configurator' ),
			__( 'Add choice', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'post-new.php?post_type=wcs_extra_option'
		);

		add_submenu_page(
			'wcs-configurator',
			__( 'Choice categories', 'woo-spiegelloft-configurator' ),
			__( 'Choice categories', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'edit-tags.php?taxonomy=wcs_extra_group&post_type=wcs_extra_option'
		);
	}

	/**
	 * Render category-first customization choices page.
	 */
	public function render_choices_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-spiegelloft-configurator' ) );
		}

		$all_groups    = $this->registry->get_groups();
		$group_options = array();
		$group_position_settings = get_option( 'wcs_group_position_settings', array() );
		$group_position_settings = is_array( $group_position_settings ) ? $group_position_settings : array();
		$total_options = 0;

		foreach ( array_keys( $all_groups ) as $group_slug ) {
			$options                     = $this->catalog->get_options_by_group( $group_slug );
			$group_options[ $group_slug ] = $options;
			$total_options              += count( $options );
		}

		include WCS_PLUGIN_DIR . 'templates/admin/choices-categories-page.php';
	}

	/**
	 * Customize list table columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function list_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['wcs_thumbnail'] = __( 'Image', 'woo-spiegelloft-configurator' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wcs_category'] = __( 'Category', 'woo-spiegelloft-configurator' );
				$new['wcs_price']    = __( 'Price', 'woo-spiegelloft-configurator' );
			}
		}
		unset( $new['taxonomy-wcs_extra_group'] );
		return $new;
	}

	/**
	 * Render custom list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_list_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'wcs_thumbnail':
				$image = (string) get_post_meta( $post_id, '_wcs_image', true );
				if ( $image ) {
					echo '<img src="' . esc_url( $image ) . '" alt="" style="max-width:48px;height:auto;">';
				} else {
					echo '—';
				}
				break;

			case 'wcs_category':
				$terms = wp_get_object_terms( $post_id, 'wcs_extra_group' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$group = $this->registry->get_group( (string) $terms[0]->slug );
					echo esc_html( (string) ( $group['label'] ?? $terms[0]->name ) );
				} else {
					echo '—';
				}
				break;

			case 'wcs_price':
				$price = get_post_meta( $post_id, '_wcs_price', true );
				echo wp_kses_post( wc_price( (float) $price ) );
				break;
		}
	}

	/**
	 * Taxonomy filter dropdown on choices list.
	 *
	 * @param string $post_type Post type.
	 */
	public function taxonomy_filter_dropdown( string $post_type ): void {
		if ( 'wcs_extra_option' !== $post_type ) {
			return;
		}

		$selected = isset( $_GET['wcs_extra_group'] ) ? sanitize_title( wp_unslash( (string) $_GET['wcs_extra_group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$groups   = $this->registry->get_groups();

		echo '<select name="wcs_extra_group" id="wcs_extra_group_filter">';
		echo '<option value="">' . esc_html__( 'All categories', 'woo-spiegelloft-configurator' ) . '</option>';
		foreach ( $groups as $slug => $group ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $selected, $slug, false ),
				esc_html( (string) ( $group['label'] ?? $slug ) )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply taxonomy filter on choices list.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function filter_by_taxonomy( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'wcs_extra_option' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $_GET['wcs_extra_group'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$slug = sanitize_title( wp_unslash( (string) $_GET['wcs_extra_group'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'wcs_extra_group',
					'field'    => 'slug',
					'terms'    => $slug,
				),
			)
		);
	}

	/**
	 * AJAX: get options for a group.
	 */
	public function ajax_get_group_options(): void {
		check_ajax_referer( 'wcs_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$group   = isset( $_POST['group'] ) ? sanitize_title( wp_unslash( (string) $_POST['group'] ) ) : '';
		$options = $this->catalog->get_options_by_group( $group );

		wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * AJAX: delete a customization choice.
	 */
	public function ajax_delete_choice(): void {
		$choice_id = isset( $_POST['choice_id'] ) ? absint( wp_unslash( $_POST['choice_id'] ) ) : 0;

		check_ajax_referer( 'wcs_delete_choice_' . $choice_id, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'delete_post', $choice_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$post = get_post( $choice_id );
		if ( ! $post || 'wcs_extra_option' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Choice not found.', 'woo-spiegelloft-configurator' ) ), 404 );
		}

		$deleted = wp_trash_post( $choice_id );
		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete this choice.', 'woo-spiegelloft-configurator' ) ), 500 );
		}

		$this->catalog->invalidate_cache( $choice_id );

		wp_send_json_success(
			array(
				'choice_id' => $choice_id,
				'message'   => __( 'Choice deleted.', 'woo-spiegelloft-configurator' ),
			)
		);
	}

	/**
	 * AJAX: save inline position choices.
	 */
	public function ajax_save_choice_positions(): void {
		$choice_id = isset( $_POST['choice_id'] ) ? absint( wp_unslash( $_POST['choice_id'] ) ) : 0;

		check_ajax_referer( 'wcs_choice_positions_' . $choice_id, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $choice_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$post = get_post( $choice_id );
		if ( ! $post || 'wcs_extra_option' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Choice not found.', 'woo-spiegelloft-configurator' ) ), 404 );
		}

		$option_data = get_post_meta( $choice_id, '_wcs_option_data', true );
		$option_data = is_array( $option_data ) ? $option_data : array();

		$enabled = ! empty( $_POST['enabled'] );
		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '';
		$rows    = isset( $_POST['positions'] ) ? wp_unslash( $_POST['positions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$positions = array();

		if ( $enabled && is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$row_label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$row_value = sanitize_title( (string) ( $row['value'] ?? $row_label ) );
				if ( '' === $row_label && '' === $row_value ) {
					continue;
				}
				$positions[] = array(
					'label' => $row_label ?: $row_value,
					'value' => $row_value,
				);
			}
		}

		unset( $option_data['position_enabled'], $option_data['position_label'], $option_data['position_options'] );
		if ( $enabled && ! empty( $positions ) ) {
			$option_data['position_enabled'] = true;
			$option_data['position_label']   = $label ?: sprintf(
				/* translators: %s: choice title */
				__( 'Position of the %s', 'woo-spiegelloft-configurator' ),
				get_the_title( $choice_id )
			);
			$option_data['position_options'] = $positions;
		}

		update_post_meta( $choice_id, '_wcs_option_data', $option_data );
		$this->catalog->invalidate_cache( $choice_id );

		wp_send_json_success( array( 'message' => __( 'Positions saved.', 'woo-spiegelloft-configurator' ) ) );
	}

	/**
	 * AJAX: save category-level position choices.
	 */
	public function ajax_save_group_positions(): void {
		$group = isset( $_POST['group'] ) ? sanitize_title( wp_unslash( (string) $_POST['group'] ) ) : '';

		check_ajax_referer( 'wcs_group_positions_' . $group, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) || ! $this->registry->get_group( $group ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-spiegelloft-configurator' ) ), 403 );
		}

		$enabled   = ! empty( $_POST['enabled'] );
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '';
		$show_when = isset( $_POST['show_when'] ) ? sanitize_title( wp_unslash( (string) $_POST['show_when'] ) ) : '';
		$rows      = isset( $_POST['positions'] ) ? wp_unslash( $_POST['positions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$positions = array();

		if ( $enabled && is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$row_label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$row_value = sanitize_title( (string) ( $row['value'] ?? $row_label ) );
				if ( '' === $row_label && '' === $row_value ) {
					continue;
				}
				$positions[] = array(
					'label' => $row_label ?: $row_value,
					'value' => $row_value,
				);
			}
		}

		$settings = get_option( 'wcs_group_position_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		unset( $settings[ $group ] );

		if ( $enabled && ! empty( $positions ) ) {
			$settings[ $group ] = array(
				'enabled'   => true,
				'label'     => $label ?: __( 'Position', 'woo-spiegelloft-configurator' ),
				'show_when' => $show_when,
				'options'   => $positions,
			);
		}

		update_option( 'wcs_group_position_settings', $settings, false );
		$this->catalog->invalidate_cache( 0 );

		wp_send_json_success( array( 'message' => __( 'Positions saved.', 'woo-spiegelloft-configurator' ) ) );
	}
}
