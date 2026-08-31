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
	 * Extras catalog.
	 *
	 * @var WCS_Extras_Catalog
	 */
	private WCS_Extras_Catalog $catalog;

	/**
	 * Constructor.
	 *
	 * @param WCS_Template        $template Template helper.
	 * @param WCS_Extras_Registry $registry Extras registry.
	 * @param WCS_Extras_Catalog  $catalog  Extras catalog.
	 */
	public function __construct( WCS_Template $template, WCS_Extras_Registry $registry, WCS_Extras_Catalog $catalog ) {
		$this->template = $template;
		$this->registry = $registry;
		$this->catalog  = $catalog;
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
			__( 'Mirror Layouts', 'woo-spiegelloft-configurator' ),
			__( 'Mirror Layouts', 'woo-spiegelloft-configurator' ),
			'manage_woocommerce',
			'edit.php?post_type=wcs_template'
		);
	}

	/**
	 * Register template meta boxes.
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wcs_template_basic',
			__( 'Basic settings', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_basic_meta_box' ),
			'wcs_template',
			'normal',
			'high'
		);

		add_meta_box(
			'wcs_template_choices',
			__( 'Customer options', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_choices_meta_box' ),
			'wcs_template',
			'normal',
			'default'
		);

		add_meta_box(
			'wcs_template_restrictions',
			__( 'Restrictions', 'woo-spiegelloft-configurator' ),
			array( $this, 'render_restrictions_meta_box' ),
			'wcs_template',
			'normal',
			'default'
		);
	}

	/**
	 * Render basic settings meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_basic_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wcs_save_template', 'wcs_template_nonce' );

		$data = $this->template->get_template_data( (int) $post->ID ) ?? array();

		include WCS_PLUGIN_DIR . 'templates/admin/template-basic-meta.php';
	}

	/**
	 * Render customer choices meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_choices_meta_box( WP_Post $post ): void {
		$data           = $this->template->get_template_data( (int) $post->ID ) ?? array();
		$enabled_groups = (array) ( $data['enabled_groups'] ?? $data['groups'] ?? array() );
		$option_map     = (array) ( $data['extra_option_map'] ?? array() );
		$all_groups     = $this->registry->get_groups();
		$group_order    = (array) ( $data['group_order'] ?? array() );
		$step_map       = (array) ( $data['step_map'] ?? array() );
		$group_options  = array();

		if ( ! empty( $group_order ) ) {
			$ordered_groups = array();
			foreach ( $group_order as $group_slug ) {
				$group_slug = (string) $group_slug;
				if ( isset( $all_groups[ $group_slug ] ) ) {
					$ordered_groups[ $group_slug ] = $all_groups[ $group_slug ];
				}
			}
			$all_groups = $ordered_groups + $all_groups;
		}

		$flat_group_slug = '';
		$flat_group      = array();
		foreach ( $all_groups as $group_slug => $group ) {
			if ( 'static' === ( $group['type'] ?? 'selectable' ) ) {
				continue;
			}
			$flat_group_slug = (string) $group_slug;
			$flat_group      = (array) $group;
			break;
		}

		$flat_options      = $this->catalog->get_all_options();
		$flat_selected_ids = array();
		foreach ( $option_map as $selected_ids ) {
			foreach ( (array) $selected_ids as $selected_id ) {
				$selected_id = absint( $selected_id );
				if ( $selected_id ) {
					$flat_selected_ids[] = $selected_id;
				}
			}
		}
		$flat_selected_ids = array_values( array_unique( $flat_selected_ids ) );
		$flat_step         = max( 1, absint( $step_map[ $flat_group_slug ] ?? 2 ) );

		$uncategorized_options = $this->catalog->get_uncategorized_options();
		foreach ( array_keys( $all_groups ) as $group_slug ) {
			$options = $this->catalog->get_options_by_group( $group_slug );
			if ( ! empty( $uncategorized_options ) ) {
				$seen = array();
				foreach ( $options as $option ) {
					$seen[ (int) ( $option['id'] ?? 0 ) ] = true;
				}
				foreach ( $uncategorized_options as $option ) {
					$option_id = (int) ( $option['id'] ?? 0 );
					if ( $option_id && ! isset( $seen[ $option_id ] ) ) {
						$options[] = $option;
					}
				}
			}
			$group_options[ $group_slug ] = $options;
		}

		include WCS_PLUGIN_DIR . 'templates/admin/template-choices-meta.php';
	}

	/**
	 * Render restrictions meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_restrictions_meta_box( WP_Post $post ): void {
		$data  = $this->template->get_template_data( (int) $post->ID ) ?? array();
		$rules = (array) ( $data['validation_rules'] ?? $data['rules'] ?? array() );
		$groups = $this->registry->get_groups();
		$option_map = (array) ( $data['extra_option_map'] ?? array() );
		$choice_step_map = (array) ( $data['choice_step_map'] ?? array() );
		$dimensions = (array) ( $data['dimensions'] ?? array() );
		$side_measurements = ! empty( $dimensions['side_measurements'] ) && is_array( $dimensions['side_measurements'] )
			? array_values( $dimensions['side_measurements'] )
			: array(
				array(
					'label' => __( 'Width', 'woo-spiegelloft-configurator' ),
					'key'   => 'width',
				),
				array(
					'label' => __( 'Height', 'woo-spiegelloft-configurator' ),
					'key'   => 'height',
				),
			);
		$option_choices = array();
		$template_field_choices = array();
		$measurement_field_choices = array();

		foreach ( $side_measurements as $measurement ) {
			$label = (string) ( $measurement['label'] ?? '' );
			$key   = sanitize_title( (string) ( $measurement['key'] ?? $label ) );
			if ( '' === $label || '' === $key ) {
				continue;
			}
			$measurement_field_choices[ 'side_' . str_replace( '-', '_', $key ) ] = $label;
		}

		foreach ( $groups as $group_slug => $group ) {
			$group_slug = (string) $group_slug;
			$selected_ids = array_values( array_filter( array_map( 'absint', (array) ( $option_map[ $group_slug ] ?? array() ) ) ) );
			if ( empty( $selected_ids ) ) {
				continue;
			}
			$options = array();
			foreach ( $selected_ids as $option_id ) {
				$option = $this->catalog->get_option( $option_id );
				if ( $option ) {
					$options[] = $option;
				}
			}
			foreach ( $options as $option ) {
				$meta        = (array) ( $option['meta'] ?? array() );
				$option_data = (array) ( $meta['_wcs_option_data'] ?? array() );
				$value       = (string) ( $option_data['value'] ?? $option['slug'] ?? '' );
				$label       = (string) ( $option_data['name'] ?? $option['title'] ?? $value );
				if ( '' === $value ) {
					continue;
				}
				$field_key = ! empty( $choice_step_map )
					? $group_slug . '__choice_' . (int) ( $option['id'] ?? 0 )
					: $group_slug;
				$template_field_choices[ $field_key ] = $label;
				$option_choices[ $group_slug ][] = array(
					'value' => $value,
					'label' => $label,
					'group' => (string) ( $group['label'] ?? $group_slug ),
				);
			}
		}

		if ( empty( $template_field_choices ) ) {
			foreach ( $this->catalog->get_all_options() as $option ) {
				$meta        = (array) ( $option['meta'] ?? array() );
				$option_data = (array) ( $meta['_wcs_option_data'] ?? array() );
				$value       = (string) ( $option_data['value'] ?? $option['slug'] ?? '' );
				$label       = (string) ( $option_data['name'] ?? $option['title'] ?? $value );
				$group_slug  = (string) ( $option['group'] ?? '' );
				if ( '' === $value ) {
					continue;
				}
				$field_key = '' !== $group_slug
					? ( ! empty( $choice_step_map ) ? $group_slug . '__choice_' . (int) ( $option['id'] ?? 0 ) : $group_slug )
					: $value;
				$template_field_choices[ $field_key ] = $label;
				$option_choices[ $group_slug ?: 'choices' ][] = array(
					'value' => $value,
					'label' => $label,
					'group' => __( 'Choices', 'woo-spiegelloft-configurator' ),
				);
			}
		}

		include WCS_PLUGIN_DIR . 'templates/admin/template-restrictions-meta.php';
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

		$enabled_groups_raw = isset( $_POST['wcs_enabled_groups'] ) ? (array) wp_unslash( $_POST['wcs_enabled_groups'] ) : array();
		$group_order_raw    = isset( $_POST['wcs_group_order'] ) ? (array) wp_unslash( $_POST['wcs_group_order'] ) : array();
		$step_map_raw       = isset( $_POST['wcs_group_steps'] ) ? wp_unslash( $_POST['wcs_group_steps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$choice_step_raw    = isset( $_POST['wcs_choice_steps'] ) ? wp_unslash( $_POST['wcs_choice_steps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$choice_order_raw   = isset( $_POST['wcs_choice_order'] ) ? (array) wp_unslash( $_POST['wcs_choice_order'] ) : array();
		$side_measurements_raw = isset( $_POST['wcs_side_measurements'] ) ? wp_unslash( $_POST['wcs_side_measurements'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$enabled_groups     = array_values( array_unique( array_map( 'sanitize_text_field', $enabled_groups_raw ) ) );
		$group_order        = array_values( array_unique( array_map( 'sanitize_text_field', $group_order_raw ) ) );
		$choice_order       = array_values( array_unique( array_filter( array_map( 'absint', $choice_order_raw ) ) ) );
		$step_map           = array();
		$choice_step_map    = array();
		$side_measurements  = array();
		if ( is_array( $step_map_raw ) ) {
			foreach ( $step_map_raw as $group_slug => $step ) {
				$group_slug = sanitize_text_field( (string) $group_slug );
				$step       = max( 1, absint( $step ) );
				if ( '' !== $group_slug ) {
					$step_map[ $group_slug ] = $step;
				}
			}
		}
		if ( is_array( $choice_step_raw ) ) {
			foreach ( $choice_step_raw as $choice_id => $step ) {
				$choice_id = absint( $choice_id );
				if ( $choice_id ) {
					$choice_step_map[ $choice_id ] = max( 1, absint( $step ) );
				}
			}
		}
		if ( is_array( $side_measurements_raw ) ) {
			foreach ( $side_measurements_raw as $side ) {
				if ( ! is_array( $side ) ) {
					continue;
				}
				$label = sanitize_text_field( (string) ( $side['label'] ?? '' ) );
				$key   = sanitize_title( (string) ( $side['key'] ?? $label ) );
				if ( '' === $label || '' === $key ) {
					continue;
				}
				$side_measurements[] = array(
					'label' => $label,
					'key'   => $key,
					'min'   => absint( $side['min'] ?? $side['min_width'] ?? $side['min_height'] ?? 400 ),
					'max'   => absint( $side['max'] ?? $side['max_width'] ?? $side['max_height'] ?? 2500 ),
				);
			}
		}
		$primary_side = $side_measurements[0] ?? array();
		if ( ! empty( $group_order ) ) {
			$enabled_lookup = array_flip( $enabled_groups );
			$enabled_groups = array_values(
				array_filter(
					$group_order,
					static function ( string $group_slug ) use ( $enabled_lookup ): bool {
						return isset( $enabled_lookup[ $group_slug ] );
					}
				)
			);
		}

		$option_map_raw = isset( $_POST['wcs_extra_option_map'] ) ? wp_unslash( $_POST['wcs_extra_option_map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$option_map     = array();
		if ( is_array( $option_map_raw ) ) {
			foreach ( $option_map_raw as $group => $ids ) {
				$group = sanitize_text_field( (string) $group );
				$selected_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
				if ( ! empty( $choice_order ) && ! empty( $selected_ids ) ) {
					$selected_lookup = array_flip( $selected_ids );
					$ordered_ids     = array_values(
						array_filter(
							$choice_order,
							static function ( int $choice_id ) use ( $selected_lookup ): bool {
								return isset( $selected_lookup[ $choice_id ] );
							}
						)
					);
					$selected_ids    = array_values( array_unique( array_merge( $ordered_ids, $selected_ids ) ) );
				}
				$option_map[ $group ] = $selected_ids;
			}
		}

		$rules = array();
		if ( isset( $_POST['wcs_validation_rules'] ) && is_array( $_POST['wcs_validation_rules'] ) ) {
			$raw_rules = wp_unslash( $_POST['wcs_validation_rules'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $raw_rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				$conditions = array();
				$raw_conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
				foreach ( $raw_conditions as $condition ) {
					if ( ! is_array( $condition ) ) {
						continue;
					}
					$path     = sanitize_text_field( (string) ( $condition['path'] ?? '' ) );
					$operator = sanitize_text_field( (string) ( $condition['operator'] ?? 'equals' ) );
					if ( '' === $path ) {
						continue;
					}
					$conditions[] = array(
						'source'   => sanitize_text_field( (string) ( $condition['source'] ?? 'category' ) ),
						'path'     => $path,
						'field'    => sanitize_text_field( (string) ( $condition['field'] ?? 'value' ) ),
						'type'     => sanitize_text_field( (string) ( $condition['type'] ?? 'selection' ) ),
						'operator' => $operator,
						'value'    => sanitize_text_field( (string) ( $condition['value'] ?? '' ) ),
					);
				}
				if ( empty( $conditions ) ) {
					$when_operator  = sanitize_text_field( (string) ( $rule['when_operator'] ?? 'equals' ) );
					$when_source    = sanitize_text_field( (string) ( $rule['when_source'] ?? 'category' ) );
					$when_path      = sanitize_text_field( (string) ( $rule['when_path'] ?? $rule['when_group'] ?? '' ) );
					if ( '' !== $when_path ) {
						$conditions[] = array(
							'source'   => $when_source,
							'path'     => $when_path,
							'field'    => sanitize_text_field( (string) ( $rule['when_field'] ?? 'value' ) ),
							'type'     => 'dimension' === $when_source ? 'number' : 'selection',
							'operator' => $when_operator,
							'value'    => sanitize_text_field( (string) ( $rule['when_value'] ?? '' ) ),
						);
					}
				}

				$actions = array();
				$raw_actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
				foreach ( $raw_actions as $action ) {
					if ( ! is_array( $action ) ) {
						continue;
					}
					$action_name = sanitize_text_field( (string) ( $action['action'] ?? '' ) );
					if ( '' === $action_name ) {
						continue;
					}
					$actions[] = array(
						'action'       => $action_name,
						'target_type'  => sanitize_text_field( (string) ( $action['target_type'] ?? 'category' ) ),
						'target'       => sanitize_text_field( (string) ( $action['target'] ?? '' ) ),
						'target_value' => sanitize_text_field( (string) ( $action['target_value'] ?? '' ) ),
						'value'        => sanitize_text_field( (string) ( $action['value'] ?? '' ) ),
						'min'          => sanitize_text_field( (string) ( $action['min'] ?? '' ) ),
						'max'          => sanitize_text_field( (string) ( $action['max'] ?? $action['value'] ?? '' ) ),
					);
				}
				if ( empty( $actions ) ) {
					$actions[] = array(
						'action'       => sanitize_text_field( (string) ( $rule['then'] ?? 'require' ) ),
						'target_type'  => sanitize_text_field( (string) ( $rule['target_type'] ?? 'category' ) ),
						'target'       => sanitize_text_field( (string) ( $rule['target'] ?? '' ) ),
						'target_value' => sanitize_text_field( (string) ( $rule['target_value'] ?? '' ) ),
						'value'        => sanitize_text_field( (string) ( $rule['max'] ?? '' ) ),
						'min'          => sanitize_text_field( (string) ( $rule['min'] ?? '' ) ),
						'max'          => sanitize_text_field( (string) ( $rule['max'] ?? '' ) ),
					);
				}

				$primary_condition = $conditions[0] ?? array();
				$primary_action    = $actions[0] ?? array();
				$when              = array();
				$when_path         = (string) ( $primary_condition['path'] ?? '' );
				$when_operator     = (string) ( $primary_condition['operator'] ?? 'equals' );
				if ( '' !== $when_path ) {
					$when[ $when_path ] = (string) ( $primary_condition['value'] ?? '' );
				}
				$rules[] = array(
					'when'          => $when,
					'match'         => sanitize_text_field( (string) ( $rule['match'] ?? 'all' ) ),
					'conditions'    => $conditions,
					'actions'       => $actions,
					'when_source'   => (string) ( $primary_condition['source'] ?? 'category' ),
					'when_path'     => $when_path,
					'when_field'    => (string) ( $primary_condition['field'] ?? 'value' ),
					'when_operator' => $when_operator,
					'rule_type'     => sanitize_text_field( (string) ( $rule['rule_type'] ?? 'required_field' ) ),
					'then'          => (string) ( $primary_action['action'] ?? 'require' ),
					'target_type'   => (string) ( $primary_action['target_type'] ?? 'category' ),
					'target'        => (string) ( $primary_action['target'] ?? '' ),
					'target_value'  => (string) ( $primary_action['target_value'] ?? '' ),
					'min'           => (string) ( $primary_action['min'] ?? '' ),
					'max'           => (string) ( $primary_action['max'] ?? $primary_action['value'] ?? '' ),
					'message'       => sanitize_text_field( (string) ( $rule['message'] ?? '' ) ),
					'error_seconds' => absint( $rule['error_seconds'] ?? 4 ),
					'restore'       => ! empty( $rule['restore'] ),
				);
			}
		}

		$edge_override = array();
		if ( isset( $_POST['wcs_edge_name'] ) ) {
			$edge_override['name'] = sanitize_text_field( wp_unslash( (string) $_POST['wcs_edge_name'] ) );
		}
		if ( isset( $_POST['wcs_edge_desc'] ) ) {
			$edge_override['desc'] = sanitize_text_field( wp_unslash( (string) $_POST['wcs_edge_desc'] ) );
		}

		$template_title = sanitize_text_field( (string) get_the_title( $post_id ) );
		$template_slug  = sanitize_title( wp_unslash( (string) ( $_POST['wcs_template_slug'] ?? '' ) ) );

		if ( '' === $template_slug || 'auto-draft' === $template_slug ) {
			$template_slug = sanitize_title( $template_title );
		}

		$this->template->save_template_data(
			$post_id,
			array(
				'panel_template'    => $template_title,
				'slug'              => $template_slug,
				'type'              => sanitize_text_field( wp_unslash( (string) ( $_POST['wcs_template_type'] ?? '' ) ) ),
				'dimensions'        => array(
					'measurement_mode'  => 'sidewise',
					'min_width'         => absint( $_POST['wcs_min_width'] ?? $primary_side['min'] ?? 400 ),
					'max_width'         => absint( $_POST['wcs_max_width'] ?? $primary_side['max'] ?? 2500 ),
					'min_height'        => absint( $_POST['wcs_min_height'] ?? $primary_side['min'] ?? 400 ),
					'max_height'        => absint( $_POST['wcs_max_height'] ?? $primary_side['max'] ?? 2500 ),
					'top_min_width'     => absint( $_POST['wcs_top_min_width'] ?? $primary_side['min'] ?? 400 ),
					'top_max_width'     => absint( $_POST['wcs_top_max_width'] ?? $primary_side['max'] ?? 2500 ),
					'bottom_min_width'  => absint( $_POST['wcs_bottom_min_width'] ?? $primary_side['min'] ?? 400 ),
					'bottom_max_width'  => absint( $_POST['wcs_bottom_max_width'] ?? $primary_side['max'] ?? 2500 ),
					'left_min_height'   => absint( $_POST['wcs_left_min_height'] ?? $primary_side['min'] ?? 400 ),
					'left_max_height'   => absint( $_POST['wcs_left_max_height'] ?? $primary_side['max'] ?? 2500 ),
					'right_min_height'  => absint( $_POST['wcs_right_min_height'] ?? $primary_side['min'] ?? 400 ),
					'right_max_height'  => absint( $_POST['wcs_right_max_height'] ?? $primary_side['max'] ?? 2500 ),
					'side_measurements' => $side_measurements,
				),
				'enabled_groups'    => $enabled_groups,
				'group_order'       => $group_order,
				'choice_order'      => $choice_order,
				'step_map'          => $step_map,
				'choice_step_map'   => $choice_step_map,
				'extra_option_map'  => $option_map,
				'validation_rules'  => $rules,
				'behavior_rules'    => $rules,
				'edge_override'     => $edge_override,
			)
		);
	}
}
