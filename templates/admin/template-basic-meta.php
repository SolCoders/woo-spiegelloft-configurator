<?php
/**
 * Template basic settings meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<string, mixed> $data Template data.
 */

defined( 'ABSPATH' ) || exit;

$dimensions = (array) ( $data['dimensions'] ?? array() );
$edge       = (array) ( $data['edge_override'] ?? array() );
?>
<div class="wcs-template-basic">
	<p>
		<label for="wcs_panel_template"><strong><?php esc_html_e( 'Panel template', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<input type="text" class="regular-text" name="wcs_panel_template" id="wcs_panel_template" value="<?php echo esc_attr( (string) ( $data['panel_template'] ?? 'bathroomMirror' ) ); ?>">
	</p>
	<p>
		<label for="wcs_template_slug"><strong><?php esc_html_e( 'Template slug', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<input type="text" class="regular-text" name="wcs_template_slug" id="wcs_template_slug" value="<?php echo esc_attr( (string) ( $data['slug'] ?? '' ) ); ?>">
	</p>
	<p>
		<label for="wcs_template_type"><strong><?php esc_html_e( 'Sandblasting / type', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<input type="text" class="regular-text" name="wcs_template_type" id="wcs_template_type" value="<?php echo esc_attr( (string) ( $data['type'] ?? '' ) ); ?>">
	</p>

	<fieldset class="wcs-dimensions-grid">
		<legend><strong><?php esc_html_e( 'Size limits (mm)', 'woo-spiegelloft-configurator' ); ?></strong></legend>
		<label><?php esc_html_e( 'Min width', 'woo-spiegelloft-configurator' ); ?>
			<input type="number" name="wcs_min_width" value="<?php echo esc_attr( (string) ( $dimensions['min_width'] ?? 400 ) ); ?>" min="0">
		</label>
		<label><?php esc_html_e( 'Max width', 'woo-spiegelloft-configurator' ); ?>
			<input type="number" name="wcs_max_width" value="<?php echo esc_attr( (string) ( $dimensions['max_width'] ?? 2500 ) ); ?>" min="0">
		</label>
		<label><?php esc_html_e( 'Min height', 'woo-spiegelloft-configurator' ); ?>
			<input type="number" name="wcs_min_height" value="<?php echo esc_attr( (string) ( $dimensions['min_height'] ?? 400 ) ); ?>" min="0">
		</label>
		<label><?php esc_html_e( 'Max height', 'woo-spiegelloft-configurator' ); ?>
			<input type="number" name="wcs_max_height" value="<?php echo esc_attr( (string) ( $dimensions['max_height'] ?? 2500 ) ); ?>" min="0">
		</label>
	</fieldset>

	<fieldset class="wcs-edge-override">
		<legend><strong><?php esc_html_e( 'Edge display (static group)', 'woo-spiegelloft-configurator' ); ?></strong></legend>
		<p>
			<label for="wcs_edge_name"><?php esc_html_e( 'Name', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="text" class="regular-text" name="wcs_edge_name" id="wcs_edge_name" value="<?php echo esc_attr( (string) ( $edge['name'] ?? 'Kanten' ) ); ?>">
		</p>
		<p>
			<label for="wcs_edge_desc"><?php esc_html_e( 'Description', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="text" class="regular-text" name="wcs_edge_desc" id="wcs_edge_desc" value="<?php echo esc_attr( (string) ( $edge['desc'] ?? 'geschliffen & poliert' ) ); ?>">
		</p>
	</fieldset>
</div>
