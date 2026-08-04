<?php
/**
 * Template groups meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string[]                             $enabled_groups Enabled group slugs.
 * @var array<string, array<string, mixed>> $all_groups     All group definitions.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcs-template-groups">
	<?php foreach ( $all_groups as $slug => $group ) : ?>
		<label class="wcs-checkbox-row">
			<input type="checkbox" name="wcs_template_groups[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $enabled_groups, true ) ); ?>>
			<?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?>
			<?php if ( ! empty( $group['required'] ) ) : ?>
				<em>(<?php esc_html_e( 'required group', 'woo-spiegelloft-configurator' ); ?>)</em>
			<?php endif; ?>
		</label>
	<?php endforeach; ?>
</div>