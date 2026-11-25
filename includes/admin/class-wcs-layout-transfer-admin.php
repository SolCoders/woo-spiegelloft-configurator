<?php
/**
 * WordPress admin entry points for layout transfer.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** Keeps HTTP permissions and presentation outside transfer services. */
class WCS_Layout_Transfer_Admin {

	/**
	 * Exporter service or configuration.
	 *
	 * @var WCS_Layout_Exporter
	 */
	private WCS_Layout_Exporter $exporter;
	/**
	 * Importer service or configuration.
	 *
	 * @var WCS_Layout_Importer
	 */
	private WCS_Layout_Importer $importer;

	/**
	 * Inject application services.
	 *
	 * @param WCS_Layout_Exporter $exporter Exporter.
	 * @param WCS_Layout_Importer $importer Importer.
	 */
	public function __construct( WCS_Layout_Exporter $exporter, WCS_Layout_Importer $importer ) {
		$this->exporter = $exporter;
		$this->importer = $importer;
	}

	/** Register admin-only hooks. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'add_meta_boxes_wcs_template', array( $this, 'register_panel' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_wcs_export_layout', array( $this, 'export' ) );
		add_action( 'admin_post_wcs_import_layout', array( $this, 'import' ) );
	}

	/** Shared destination for both import entry points. */
	private function import_url(): string {
		return admin_url( 'admin.php?page=wcs-layout-transfer' );
	}

	/**
	 * Nonce-protected download URL.
	 *
	 * @param int $id Id.
	 */
	private function export_url( int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'wcs_export_layout',
					'layout_id' => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'wcs_export_layout_' . $id
		);
	}

	/** Register the shared upload screen. */
	public function register_menu(): void {
		add_submenu_page( 'wcs-configurator', __( 'Import Mirror Layout', 'woo-spiegelloft-configurator' ), __( 'Import Layout', 'woo-spiegelloft-configurator' ), 'manage_woocommerce', 'wcs-layout-transfer', array( $this, 'render_page' ) );
	}

	/** Add an Import Layout button beside the native Add New button. */
	public function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-wcs_template' !== $screen->id || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_enqueue_script( 'wcs-layout-transfer', WCS_PLUGIN_URL . 'assets/js/admin-layout-transfer.js', array(), WCS_VERSION, true );
		wp_localize_script(
			'wcs-layout-transfer',
			'wcsLayoutTransfer',
			array(
				'url'   => $this->import_url(),
				'label' => __( 'Import Layout', 'woo-spiegelloft-configurator' ),
			)
		);
	}

	/**
	 * Add per-layout export links to the listing.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post Post.
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		if ( 'wcs_template' === $post->post_type && $this->can_export( $post ) ) {
			$actions['wcs_export'] = '<a href="' . esc_url( $this->export_url( (int) $post->ID ) ) . '">' . esc_html__( 'Export JSON', 'woo-spiegelloft-configurator' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Determine whether persisted layout content can be downloaded.
	 *
	 * @param WP_Post $post Post.
	 */
	private function can_export( WP_Post $post ): bool {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $post->ID ) && in_array( $post->post_status, array( 'publish', 'draft' ), true ) && metadata_exists( 'post', $post->ID, '_wcs_template_data' );
	}

	/** Register transfer panel on individual layout pages. */
	public function register_panel(): void {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			add_meta_box( 'wcs_layout_transfer', __( 'Import / Export', 'woo-spiegelloft-configurator' ), array( $this, 'render_panel' ), 'wcs_template', 'side' );
		}
	}

	/**
	 * Render links only: do not nest an upload form inside the post form.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_panel( WP_Post $post ): void {
		$can_export = $this->can_export( $post );
		include WCS_PLUGIN_DIR . 'templates/admin/layout-transfer-panel.php';
	}

	/** Render shared upload form and a one-time per-user result. */
	public function render_page(): void {
		$this->authorize();
		$result = get_transient( 'wcs_layout_result_' . get_current_user_id() );
		delete_transient( 'wcs_layout_result_' . get_current_user_id() );
		$max_bytes = min( WCS_Layout_Package::MAX_BYTES, wp_max_upload_size() );
		include WCS_PLUGIN_DIR . 'templates/admin/layout-transfer.php';
	}

	/** Require the plugin management capability for every HTTP entry point. */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to transfer mirror layouts.', 'woo-spiegelloft-configurator' ), '', array( 'response' => 403 ) );
		}
	}

	/** Download persisted settings as a single standalone JSON file. */
	public function export(): void {
		$this->authorize();
		$id = isset( $_GET['layout_id'] ) ? absint( $_GET['layout_id'] ) : 0;
		check_admin_referer( 'wcs_export_layout_' . $id );
		wp_raise_memory_limit( 'admin' );
		$post = get_post( $id );
		if ( ! $post || 'wcs_template' !== $post->post_type || ! $this->can_export( $post ) ) {
			wp_die( esc_html__( 'This saved mirror layout cannot be exported.', 'woo-spiegelloft-configurator' ), '', array( 'response' => 403 ) );
		}
		$json = $this->exporter->export( $id );
		if ( is_wp_error( $json ) ) {
			$this->finish( array( 'error' => $json->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="mirror-layout-' . $id . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/** Validate upload transport and permissions before invoking the importer. */
	public function import(): void {
		$this->authorize();
		check_admin_referer( 'wcs_import_layout' );
		wp_raise_memory_limit( 'admin' );
		foreach ( array( 'wcs_template', 'wcs_extra_option' ) as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object || ! current_user_can( $object->cap->create_posts ) || ! current_user_can( $object->cap->publish_posts ) ) {
				wp_die( esc_html__( 'You cannot create imported layouts and customer options.', 'woo-spiegelloft-configurator' ), '', array( 'response' => 403 ) );
			}
		}
		if ( isset( $_POST['pending_token'] ) ) {
			if ( ! is_string( $_POST['pending_token'] ) ) {
				$this->finish( array( 'error' => __( 'Invalid pending import. Please upload the JSON again.', 'woo-spiegelloft-configurator' ) ) );
			}
			$token = sanitize_key( wp_unslash( (string) $_POST['pending_token'] ) );
			$key   = $this->pending_key( $token );
			$json  = get_transient( $key );
			if ( ! is_string( $json ) ) {
				$this->finish( array( 'error' => __( 'This pending import has expired. Please upload the JSON again.', 'woo-spiegelloft-configurator' ) ) );
			}
			$target_id = absint( $_POST['target_template_id'] ?? 0 );
			$result    = $this->importer->import( $json, $target_id );
			delete_transient( $key );
			$this->finish( is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result );
		}
		$file = $_FILES['layout_json'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified PHP upload; package contents are validated below.
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_string( $file['name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			$this->finish( array( 'error' => __( 'Choose a valid .json upload. Check the server upload limit if the upload failed.', 'woo-spiegelloft-configurator' ) ) );
		}
		$limit = min( WCS_Layout_Package::MAX_BYTES, wp_max_upload_size() );
		$size  = filesize( $file['tmp_name'] );
		if ( false === $size || 0 === $size || $size > $limit ) {
			$this->finish( array( 'error' => __( 'The JSON upload is empty or exceeds the allowed size.', 'woo-spiegelloft-configurator' ) ) );
		}
		$json = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified PHP upload.
		if ( false === $json ) {
			$this->finish( array( 'error' => __( 'Unable to read the JSON upload.', 'woo-spiegelloft-configurator' ) ) );
		}
		$result = $this->importer->import( $json );
		if ( is_wp_error( $result ) && 'wcs_layout_ambiguous' === $result->get_error_code() ) {
			$token = wp_generate_uuid4();
			if ( ! set_transient( $this->pending_key( $token ), $json, 15 * MINUTE_IN_SECONDS ) ) {
				$this->finish( array( 'error' => __( 'Unable to retain the upload for target selection. Please try again.', 'woo-spiegelloft-configurator' ) ) );
			}
			$this->finish(
				array(
					'pending_token' => $token,
					'matches'       => $result->get_error_data()['matches'],
				)
			);
		}
		$this->finish( is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result );
	}

	/**
	 * Pending uploads are bound to their uploading user and expire automatically.
	 *
	 * @param string $token Random upload token.
	 * @return string
	 */
	private function pending_key( string $token ): string {
		return 'wcs_layout_pending_' . get_current_user_id() . '_' . md5( $token );
	}

	/**
	 * Use POST/redirect/GET so refreshing does not import another copy.
	 *
	 * @param array $result Result.
	 */
	private function finish( array $result ): void {
		set_transient( 'wcs_layout_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( $this->import_url() );
		exit;
	}
}
