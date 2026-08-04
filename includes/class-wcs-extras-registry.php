<?php
/**
 * Extra groups registry.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Extras_Registry
 */
class WCS_Extras_Registry {

	/**
	 * Registered group definitions.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $groups = null;

	/**
	 * Initialize hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'load_groups' ), 5 );
	}

	/**
	 * Load group definitions from files and filters.
	 */
	public function load_groups(): void {
		$groups = array();

		$dir = WCS_PLUGIN_DIR . 'includes/extras/groups/';
		if ( is_dir( $dir ) ) {
			$files = glob( $dir . '*.php' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$definition = include $file;
					if ( is_array( $definition ) && ! empty( $definition['slug'] ) ) {
						$groups[ (string) $definition['slug'] ] = $definition;
					}
				}
			}
		}

		$custom = get_posts(
			array(
				'post_type'      => 'wcs_extra_group_def',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $custom as $post_id ) {
			$data = get_post_meta( (int) $post_id, '_wcs_group_definition', true );
			if ( is_array( $data ) && ! empty( $data['slug'] ) ) {
				$groups[ (string) $data['slug'] ] = $data;
			}
		}

		/**
		 * Filter registered extra groups.
		 *
		 * @param array<string, array<string, mixed>> $groups Group definitions.
		 */
		$this->groups = apply_filters( 'wcs_register_extra_groups', $groups );
	}

	/**
	 * Get all groups.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_groups(): array {
		if ( null === $this->groups ) {
			$this->load_groups();
		}
		return $this->groups ?? array();
	}

	/**
	 * Get a single group by slug.
	 *
	 * @param string $slug Group slug.
	 * @return array<string, mixed>|null
	 */
	public function get_group( string $slug ): ?array {
		$groups = $this->get_groups();
		return $groups[ $slug ] ?? null;
	}

	/**
	 * Get group slugs.
	 *
	 * @return string[]
	 */
	public function get_group_slugs(): array {
		return array_keys( $this->get_groups() );
	}
}