<?php
/**
 * Extra option meta box template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string                             $slug   Option slug.
 * @var string                             $price  Option price.
 * @var string                             $image  Option image URL.
 * @var string                             $group  Current group slug.
 * @var array<string, array<string, mixed>> $groups All groups.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="form-table wcs-extra-option-meta">
	<tr>
		<th><label for="wcs_extra_group"><?php esc_html_e( 'Group', 'woo-spiegelloft-configurator' ); ?></label></th>
		<td>
			<select name="wcs_extra_group" id="wcs_extra_group">
				<option value=""><?php esc_html_e( '— Select —', 'woo-spiegelloft-configurator' ); ?></option>
				<?php foreach ( $groups as $group_slug => $group_def ) : ?>
					<option value="<?php echo esc_attr( $group_slug ); ?>" <?php selected( $group, $group_slug ); ?>>
						<?php echo esc_html( (string) ( $group_def['label'] ?? $group_slug ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="wcs_option_slug"><?php esc_html_e( 'Slug', 'woo-spiegelloft-configurator' ); ?></label></th>
		<td><input type="text" name="wcs_option_slug" id="wcs_option_slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="wcs_price"><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></label></th>
		<td><input type="number" step="0.01" min="0" name="wcs_price" id="wcs_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="wcs_image"><?php esc_html_e( 'Image URL', 'woo-spiegelloft-configurator' ); ?></label></th>
		<td>
			<input type="url" name="wcs_image" id="wcs_image" value="<?php echo esc_attr( $image ); ?>" class="regular-text wcs-image-url">
			<button type="button" class="button wcs-upload-image" data-target="wcs_image"><?php esc_html_e( 'Upload', 'woo-spiegelloft-configurator' ); ?></button>
		</td>
	</tr>
</table>