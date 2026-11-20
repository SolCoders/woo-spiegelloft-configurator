<?php
/**
 * Transfer controls inside the WordPress post editor.
 *
 * @package WooSpiegelloftConfigurator
 */

defined( 'ABSPATH' ) || exit;
?>
<p><a class="button" href="<?php echo esc_url( $this->import_url() ); ?>"><?php esc_html_e( 'Import Layout', 'woo-spiegelloft-configurator' ); ?></a></p>
<p>
	<?php if ( $can_export ) : ?>
		<a class="button" href="<?php echo esc_url( $this->export_url( (int) $post->ID ) ); ?>"><?php esc_html_e( 'Export JSON', 'woo-spiegelloft-configurator' ); ?></a>
	<?php else : ?>
		<button type="button" class="button" disabled><?php esc_html_e( 'Export JSON', 'woo-spiegelloft-configurator' ); ?></button>
	<?php endif; ?>
</p>
<p class="description"><?php esc_html_e( 'Export includes saved settings only. Save your changes before exporting.', 'woo-spiegelloft-configurator' ); ?></p>
<p class="description"><?php esc_html_e( 'Images are excluded. Upload them separately after importing.', 'woo-spiegelloft-configurator' ); ?></p>
<p class="description"><?php esc_html_e( 'Import creates a new layout and new customer options. It does not replace this layout.', 'woo-spiegelloft-configurator' ); ?></p>
