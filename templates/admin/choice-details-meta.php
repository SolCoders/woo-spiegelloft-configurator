<?php
/**
 * Choice details meta box template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var string                             $group       Selected group slug.
 * @var array<string, array<string,mixed>> $groups      All groups.
 * @var array<string, mixed>               $option_data Stored option data.
 * @var int                                $legacy_id   Legacy Shopify ID.
 * @var string                             $slug        Internal value slug.
 * @var string                             $price       Price string.
 * @var string                             $value       Option value slug.
 */

defined( 'ABSPATH' ) || exit;

$name = (string) ( $option_data['name'] ?? '' );
?>
<div class="wcs-choice-details">
	<p class="wcs-field">
		<label for="wcs_extra_group"><strong><?php esc_html_e( 'Category', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<select name="wcs_extra_group" id="wcs_extra_group" class="widefat wcs-choice-category">
			<option value=""><?php esc_html_e( '— Select category —', 'woo-spiegelloft-configurator' ); ?></option>
			<?php foreach ( $groups as $slug => $group_def ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $group, $slug ); ?>>
					<?php echo esc_html( (string) ( $group_def['label'] ?? $slug ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="wcs-field">
		<label for="wcs_option_name"><strong><?php esc_html_e( 'Customer-facing label', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<input type="text" class="widefat" name="wcs_option_name" id="wcs_option_name" value="<?php echo esc_attr( $name ); ?>">
	</p>

	<p class="wcs-field wcs-field-inline">
		<label for="wcs_price"><strong><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<input type="text" class="regular-text" name="wcs_price" id="wcs_price" value="<?php echo esc_attr( $price ); ?>" placeholder="0.00">
	</p>

	<details class="wcs-advanced">
		<summary><?php esc_html_e( 'Advanced settings', 'woo-spiegelloft-configurator' ); ?></summary>
		<p class="wcs-field">
			<label for="wcs_option_value"><?php esc_html_e( 'Internal value slug', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="text" class="widefat" name="wcs_option_value" id="wcs_option_value" value="<?php echo esc_attr( $value ); ?>">
			<span class="description"><?php esc_html_e( 'Used in configuration JSON. Usually lowercase with hyphens.', 'woo-spiegelloft-configurator' ); ?></span>
		</p>
		<p class="wcs-field">
			<label for="wcs_legacy_id"><?php esc_html_e( 'Legacy option ID', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="number" class="small-text" name="wcs_legacy_id" id="wcs_legacy_id" value="<?php echo esc_attr( (string) $legacy_id ); ?>" min="0">
		</p>
	</details>
</div>
