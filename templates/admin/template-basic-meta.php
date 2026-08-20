<?php
/**
 * Template basic settings meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<string, mixed> $data Template data.
 * @var WP_Post             $post Template post.
 */

defined( 'ABSPATH' ) || exit;

$dimensions    = (array) ( $data['dimensions'] ?? array() );
$edge          = (array) ( $data['edge_override'] ?? array() );
$template_slug = (string) ( $data['slug'] ?? '' );
$side_measurements = ! empty( $dimensions['side_measurements'] ) && is_array( $dimensions['side_measurements'] )
	? array_values( $dimensions['side_measurements'] )
	: array(
		array(
			'label' => __( 'Width', 'woo-spiegelloft-configurator' ),
			'key'   => 'width',
			'min'   => (int) ( $dimensions['min_width'] ?? 400 ),
			'max'   => (int) ( $dimensions['max_width'] ?? 2500 ),
		),
		array(
			'label' => __( 'Height', 'woo-spiegelloft-configurator' ),
			'key'   => 'height',
			'min'   => (int) ( $dimensions['min_height'] ?? 400 ),
			'max'   => (int) ( $dimensions['max_height'] ?? 2500 ),
		),
	);

if ( '' === $template_slug || 'auto-draft' === $template_slug ) {
	$template_slug = sanitize_title( (string) $post->post_title );
}
?>
<div class="wcs-template-basic">
	<div class="wcs-template-fields">
		<label class="wcs-template-field" for="wcs_template_slug">
			<span><?php esc_html_e( 'Template slug', 'woo-spiegelloft-configurator' ); ?></span>
			<input type="text" name="wcs_template_slug" id="wcs_template_slug" value="<?php echo esc_attr( $template_slug ); ?>">
		</label>
		<label class="wcs-template-field" for="wcs_template_type">
			<span><?php esc_html_e( 'Sandblasting / type', 'woo-spiegelloft-configurator' ); ?></span>
			<input type="text" name="wcs_template_type" id="wcs_template_type" value="<?php echo esc_attr( (string) ( $data['type'] ?? '' ) ); ?>">
		</label>
	</div>

	<fieldset class="wcs-dimensions-grid">
		<legend><strong><?php esc_html_e( 'Size limits (mm)', 'woo-spiegelloft-configurator' ); ?></strong></legend>
		<div class="wcs-side-dimensions">
			<div class="wcs-side-list">
				<?php foreach ( $side_measurements as $index => $side ) : ?>
					<div class="wcs-side-row">
						<label><?php esc_html_e( 'Label', 'woo-spiegelloft-configurator' ); ?>
							<input type="text" class="wcs-side-label" name="wcs_side_measurements[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) ( $side['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Mirror size', 'woo-spiegelloft-configurator' ); ?>">
						</label>
						<label><?php esc_html_e( 'Key', 'woo-spiegelloft-configurator' ); ?>
							<input type="text" class="wcs-side-key" name="wcs_side_measurements[<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( (string) ( $side['key'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'mirror-size', 'woo-spiegelloft-configurator' ); ?>">
						</label>
						<label><?php esc_html_e( 'Min', 'woo-spiegelloft-configurator' ); ?>
							<input type="number" name="wcs_side_measurements[<?php echo esc_attr( (string) $index ); ?>][min]" value="<?php echo esc_attr( (string) ( $side['min'] ?? $side['min_width'] ?? $side['min_height'] ?? 400 ) ); ?>" min="0">
						</label>
						<label><?php esc_html_e( 'Max', 'woo-spiegelloft-configurator' ); ?>
							<input type="number" name="wcs_side_measurements[<?php echo esc_attr( (string) $index ); ?>][max]" value="<?php echo esc_attr( (string) ( $side['max'] ?? $side['max_width'] ?? $side['max_height'] ?? 2500 ) ); ?>" min="0">
						</label>
						<button type="button" class="button wcs-side-add"><?php esc_html_e( 'Add', 'woo-spiegelloft-configurator' ); ?></button>
						<button type="button" class="button wcs-side-remove"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
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
