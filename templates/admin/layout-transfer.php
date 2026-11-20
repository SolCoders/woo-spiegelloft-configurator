<?php
/**
 * Shared layout upload form and transfer feedback.
 *
 * @package WooSpiegelloftConfigurator
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Import Mirror Layout', 'woo-spiegelloft-configurator' ); ?></h1>
	<?php if ( is_array( $result ) && isset( $result['error'] ) ) : ?>
		<div id="wcs-layout-transfer-result" class="notice notice-error" role="alert"><p><?php echo esc_html( $result['error'] ); ?></p></div>
	<?php elseif ( is_array( $result ) && isset( $result['template_id'] ) ) : ?>
		<div id="wcs-layout-transfer-result" class="notice notice-success" role="status"><p>
			<?php
			/* translators: %d: customer option count. */
			printf( esc_html__( 'Layout imported with %d customer options. Upload the images separately.', 'woo-spiegelloft-configurator' ), (int) $result['options'] );
			?>
			<a href="<?php echo esc_url( get_edit_post_link( (int) $result['template_id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit imported layout', 'woo-spiegelloft-configurator' ); ?></a>
		</p></div>
	<?php endif; ?>
	<p><?php esc_html_e( 'Upload a layout JSON exported by this plugin. A new template and its customer options will be created. Images are excluded; upload them separately after importing.', 'woo-spiegelloft-configurator' ); ?></p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="wcs_import_layout">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) $max_bytes ); ?>">
		<?php wp_nonce_field( 'wcs_import_layout' ); ?>
		<table class="form-table" role="presentation"><tr>
			<th scope="row"><label for="wcs-layout-json"><?php esc_html_e( 'Layout JSON file', 'woo-spiegelloft-configurator' ); ?></label></th>
			<td>
				<input id="wcs-layout-json" type="file" name="layout_json" accept=".json,application/json" required aria-describedby="wcs-layout-limit">
				<p class="description" id="wcs-layout-limit">
				<?php
				/* translators: %s: maximum upload size. */
				printf( esc_html__( 'Maximum upload: %s. Keep this page open until the import finishes.', 'woo-spiegelloft-configurator' ), esc_html( size_format( $max_bytes ) ) );
				?>
				</p>
			</td>
		</tr></table>
		<?php submit_button( __( 'Import Layout', 'woo-spiegelloft-configurator' ) ); ?>
	</form>
	<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wcs_template' ) ); ?>"><?php esc_html_e( 'Back to Mirror Layouts', 'woo-spiegelloft-configurator' ); ?></a></p>
</div>
