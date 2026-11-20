<?php
/**
 * Builds a standalone package from persisted layout settings.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** Read-only layout export service. */
class WCS_Layout_Exporter {

	/**
	 * Template service or configuration.
	 *
	 * @var WCS_Template
	 */
	private WCS_Template $template;
	/**
	 * Registry service or configuration.
	 *
	 * @var WCS_Extras_Registry
	 */
	private WCS_Extras_Registry $registry;
	/**
	 * Catalog service or configuration.
	 *
	 * @var WCS_Extras_Catalog
	 */
	private WCS_Extras_Catalog $catalog;
	/**
	 * Package service or configuration.
	 *
	 * @var WCS_Layout_Package
	 */
	private WCS_Layout_Package $package;

	/**
	 * Inject the existing data services and portable package helpers.
	 *
	 * @param WCS_Template        $template Template.
	 * @param WCS_Extras_Registry $registry Registry.
	 * @param WCS_Extras_Catalog  $catalog Catalog.
	 * @param WCS_Layout_Package  $package Package.
	 */
	public function __construct( WCS_Template $template, WCS_Extras_Registry $registry, WCS_Extras_Catalog $catalog, WCS_Layout_Package $package ) {
		$this->template = $template;
		$this->registry = $registry;
		$this->catalog  = $catalog;
		$this->package  = $package;
	}

	/**
	 * Export one saved layout to JSON.
	 *
	 * @param int $id Id.
	 * @return string|WP_Error
	 * @throws RuntimeException Internally caught and returned as WP_Error.
	 */
	public function export( int $id ) {
		$post = get_post( $id );
		if ( ! $post || 'wcs_template' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) || ! metadata_exists( 'post', $id, '_wcs_template_data' ) ) {
			return new WP_Error( 'wcs_export_layout', __( 'Save this mirror layout as a draft or publish it before exporting.', 'woo-spiegelloft-configurator' ) );
		}
		try {
			$data                          = $this->template->get_template_data( $id );
			$definitions                   = $this->registry->get_groups_for_template( $id );
			$data['extra_option_map']      = $this->catalog->get_template_option_map( $data, $definitions );
			$data['option_selection_mode'] = 'explicit';
			unset( $data['id'], $data['title'], $data['groups'], $data['rules'], $data['dependencies'] );
			$groups    = array();
			$options   = array();
			$positions = $this->template->get_position_settings( $id );
			foreach ( $data['enabled_groups'] as $slug ) {
				if ( ! isset( $definitions[ $slug ] ) ) {
					throw new RuntimeException( __( 'Missing group definition: ', 'woo-spiegelloft-configurator' ) . $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
				}
				$groups[ $slug ] = $definitions[ $slug ];
				$selected        = array_map( 'intval', (array) ( $data['extra_option_map'][ $slug ] ?? array() ) );
				if ( ! $selected ) {
					$available = array();
				} elseif ( $selected ) {
					// Prime post metadata and terms in bulk; avoid loading unrelated group members.
					$posts     = get_posts(
						array(
							'post_type'      => 'wcs_extra_option',
							'post_status'    => 'any',
							'post__in'       => $selected,
							'posts_per_page' => count( $selected ),
							'orderby'        => 'post__in',
						)
					);
					$available = array_map( array( $this->catalog, 'format_option' ), $posts );
				}
				$found = array();
				foreach ( $available as $option ) {
					if ( $selected && ! in_array( $option['id'], $selected, true ) ) {
						continue;
					}
					$option['meta']           = array_intersect_key( $option['meta'], array_flip( array( '_wcs_option_data', '_wcs_option_slug', '_wcs_price', '_wcs_legacy_id', '_wcs_image' ) ) );
					$options[ $option['id'] ] = $option;
					$found[]                  = $option['id'];
					if ( '' !== $option['group'] && isset( $definitions[ $option['group'] ] ) ) {
						$groups[ $option['group'] ] = $definitions[ $option['group'] ];
					}
				}
				if ( array_diff( $selected, $found ) ) {
					throw new RuntimeException( __( 'A selected customer option is missing in group: ', 'woo-spiegelloft-configurator' ) . $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
				}
				// Freeze implicit "all group options" to this exact dependency set.
				$data['extra_option_map'][ $slug ] = $selected ? $selected : $found;
			}
			$data['extra_option_map'] = array_intersect_key( $data['extra_option_map'], array_flip( $data['enabled_groups'] ) );
			$data['choice_order']     = array_values( array_filter( $data['choice_order'], static fn( $id ): bool => isset( $options[ $id ] ) ) );
			$data['choice_step_map']  = array_intersect_key( $data['choice_step_map'], $options );
			$data['group_order']      = array_values( array_filter( $data['group_order'], static fn( $slug ): bool => isset( $groups[ $slug ] ) ) );
			$data['step_map']         = array_intersect_key( $data['step_map'], $groups );
			$document                 = array(
				'format'    => WCS_Layout_Package::FORMAT,
				'version'   => WCS_Layout_Package::VERSION,
				'template'  => array(
					'title'  => $post->post_title,
					'status' => $post->post_status,
					'data'   => $data,
				),
				'options'   => array_values( $options ),
				'groups'    => $groups,
				'positions' => array_intersect_key( $positions, $groups ),
			);
			$valid                    = $this->package->validate( $document );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$json = wp_json_encode( $valid, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $json || strlen( $json ) > WCS_Layout_Package::MAX_BYTES ) {
				throw new RuntimeException( __( 'The layout cannot be encoded within the 50 MiB JSON limit.', 'woo-spiegelloft-configurator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Errors are escaped by the admin view.
			}
			return $json;
		} catch ( Throwable $error ) {
			return new WP_Error( 'wcs_layout_export', $error->getMessage() );
		}
	}
}
