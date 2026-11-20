<?php
/**
 * WooCommerce product configurator tab.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Product_Meta
 */
class WCS_Product_Meta {

	/**
	 * Template helper.
	 *
	 * @var WCS_Template
	 */
	private WCS_Template $template;

	/**
	 * Constructor.
	 *
	 * @param WCS_Template $template Template helper.
	 */
	public function __construct( WCS_Template $template ) {
		$this->template = $template;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_filter( 'manage_edit-product_columns', array( $this, 'add_layout_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_layout_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_layout_filter' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_products_by_layout' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_list_styles' ), 20 );
	}

	/**
	 * Reserve space in WooCommerce's fixed-width product table.
	 */
	public function enqueue_product_list_styles(): void {
		$screen = get_current_screen();
		if ( $screen && 'edit-product' === $screen->id ) {
			wp_add_inline_style( 'woocommerce_admin_styles', '.wp-list-table .column-wcs_mirror_layout { width: 12%; }' );
		}
	}

	/**
	 * Add the assigned layout beside the product name.
	 *
	 * @param array<string, string> $columns Product columns.
	 * @return array<string, string>
	 */
	public function add_layout_column( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'name' === $key ) {
				$result['wcs_mirror_layout'] = __( 'Mirror layout', 'woo-spiegelloft-configurator' );
			}
		}
		if ( ! isset( $result['wcs_mirror_layout'] ) ) {
			$result['wcs_mirror_layout'] = __( 'Mirror layout', 'woo-spiegelloft-configurator' );
		}
		return $result;
	}

	/**
	 * Display the assignment even when the product customizer is disabled.
	 *
	 * @param string $column Column key.
	 * @param int    $product_id Product ID.
	 */
	public function render_layout_column( string $column, int $product_id ): void {
		if ( 'wcs_mirror_layout' !== $column ) {
			return;
		}
		$template_id = $this->template->get_product_template_id( $product_id );
		if ( ! $template_id ) {
			esc_html_e( 'No layout attached', 'woo-spiegelloft-configurator' );
			return;
		}
		$layout = get_post( $template_id );
		if ( ! $layout || 'wcs_template' !== $layout->post_type ) {
			esc_html_e( 'Layout unavailable', 'woo-spiegelloft-configurator' );
			return;
		}
		$title = '' !== $layout->post_title ? $layout->post_title : __( '(no title)', 'woo-spiegelloft-configurator' );
		$link  = get_edit_post_link( $template_id );
		if ( $link ) {
			printf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $title ) );
		} else {
			echo esc_html( $title );
		}
	}

	/**
	 * Read the list filter. This read-only GET filter does not require a nonce.
	 */
	private function get_layout_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$value = isset( $_GET['wcs_mirror_layout'] ) && is_string( $_GET['wcs_mirror_layout'] ) ? sanitize_text_field( wp_unslash( $_GET['wcs_mirror_layout'] ) ) : '';
		return 'none' === $value || ( ctype_digit( $value ) && (int) $value > 0 ) ? $value : '';
	}

	/**
	 * Render a native filter alongside WooCommerce's existing filters.
	 *
	 * @param string $post_type Current post type.
	 * @param string $which Table navigation position.
	 */
	public function render_layout_filter( string $post_type, string $which ): void {
		if ( 'product' !== $post_type || 'top' !== $which ) {
			return;
		}
		$selected = $this->get_layout_filter();
		$layouts  = get_posts(
			array(
				'post_type'              => 'wcs_template',
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		?>
		<label class="screen-reader-text" for="wcs-mirror-layout-filter"><?php esc_html_e( 'Filter by mirror layout', 'woo-spiegelloft-configurator' ); ?></label>
		<select name="wcs_mirror_layout" id="wcs-mirror-layout-filter">
			<option value=""><?php esc_html_e( 'All mirror layouts', 'woo-spiegelloft-configurator' ); ?></option>
			<option value="none" <?php selected( $selected, 'none' ); ?>><?php esc_html_e( 'No layout attached', 'woo-spiegelloft-configurator' ); ?></option>
			<?php foreach ( $layouts as $layout ) : ?>
				<option value="<?php echo esc_attr( (string) $layout->ID ); ?>" <?php selected( $selected, (string) $layout->ID ); ?>><?php echo esc_html( '' !== $layout->post_title ? $layout->post_title : __( '(no title)', 'woo-spiegelloft-configurator' ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Constrain only the main admin product list, preserving other filters.
	 *
	 * @param WP_Query $query Product list query.
	 */
	public function filter_products_by_layout( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id || 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		$selected = $this->get_layout_filter();
		if ( '' === $selected ) {
			return;
		}
		$clause = array(
			'key'   => '_wcs_template_id',
			'value' => (int) $selected,
			'type'  => 'NUMERIC',
		);
		if ( 'none' === $selected ) {
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => '_wcs_template_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_wcs_template_id',
					'value' => 0,
					'type'  => 'NUMERIC',
				),
			);
		}
		$existing   = $query->get( 'meta_query' );
		$meta_query = empty( $existing ) ? array( $clause ) : array(
			'relation' => 'AND',
			$existing,
			$clause,
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Add configurator product data tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_product_tab( array $tabs ): array {
		$tabs['wcs_configurator'] = array(
			'label'    => __( 'Mirror Customizer', 'woo-spiegelloft-configurator' ),
			'target'   => 'wcs_configurator_product_data',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render configurator product panel.
	 */
	public function render_product_panel(): void {
		global $post;

		$product_id  = (int) $post->ID;
		$template_id = $this->template->get_product_template_id( $product_id );
		$templates   = $this->template->get_all_templates();
		$enabled     = 'yes' === get_post_meta( $product_id, '_wcs_enabled', true );

		if ( ! $enabled && 'yes' === get_post_meta( $product_id, '_wcs_configurator_enabled', true ) ) {
			$enabled = true;
		}

		include WCS_PLUGIN_DIR . 'templates/admin/product-configurator-tab.php';
	}

	/**
	 * Save product configurator meta.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_meta( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['_wcs_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wcs_enabled', $enabled );
		update_post_meta( $post_id, '_wcs_configurator_enabled', $enabled );

		if ( isset( $_POST['_wcs_template_id'] ) ) {
			$template_id = absint( $_POST['_wcs_template_id'] );
			$this->template->set_product_template_id( $post_id, $template_id );
		}
	}
}
