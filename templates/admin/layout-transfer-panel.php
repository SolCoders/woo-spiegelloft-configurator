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
<p class="description"><?php esc_html_e( 'Images are excluded from JSON. Updating a matching layout preserves its existing images.', 'woo-spiegelloft-configurator' ); ?></p>
<p class="description"><?php esc_html_e( 'Import updates a layout with the same name, including its options and settings. A new layout is created only when no name matches.', 'woo-spiegelloft-configurator' ); ?></p>
