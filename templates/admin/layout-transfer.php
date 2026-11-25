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
			if ( 'updated' === ( $result['action'] ?? 'created' ) ) {
				/* translators: %d: customer option count. */
				$message = __( 'Layout updated with %d customer options. Existing matching images were preserved.', 'woo-spiegelloft-configurator' );
			} else {
				/* translators: %d: customer option count. */
				$message = __( 'Layout created with %d customer options. Upload new images separately.', 'woo-spiegelloft-configurator' );
			}
			printf( esc_html( $message ), (int) $result['options'] );
			?>
			<a href="<?php echo esc_url( get_edit_post_link( (int) $result['template_id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit layout', 'woo-spiegelloft-configurator' ); ?></a>
		</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $result['pending_token'] ) && ! empty( $result['matches'] ) ) : ?>
		<div id="wcs-layout-transfer-select" class="notice notice-info"><p><?php esc_html_e( 'Several templates match this file. Choose which template to replace. No settings have been changed yet.', 'woo-spiegelloft-configurator' ); ?></p></div>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="wcs_import_layout">
			<input type="hidden" name="pending_token" value="<?php echo esc_attr( $result['pending_token'] ); ?>">
			<?php wp_nonce_field( 'wcs_import_layout' ); ?>
			<p><label for="wcs-layout-target"><?php esc_html_e( 'Template to update', 'woo-spiegelloft-configurator' ); ?></label></p>
			<select id="wcs-layout-target" name="target_template_id" required>
				<option value=""><?php esc_html_e( 'Choose a matching template', 'woo-spiegelloft-configurator' ); ?></option>
				<?php foreach ( $result['matches'] as $match ) : ?>
					<option value="<?php echo esc_attr( (string) $match['id'] ); ?>"><?php echo esc_html( $match['title'] . ' (#' . $match['id'] . ', ' . $match['status'] . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Update selected template', 'woo-spiegelloft-configurator' ) ); ?>
		</form>
	<?php endif; ?>
	<p><?php esc_html_e( 'Upload a layout JSON exported by this plugin. A matching template name updates that template; otherwise a new template is created. Uploaded settings replace the selected template configuration. Existing matching images are kept; new images must be uploaded separately.', 'woo-spiegelloft-configurator' ); ?></p>
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
