<?php
/**
 * Choice details meta box template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var int    $legacy_id Legacy Shopify ID.
 * @var string $slug      Internal value slug.
 * @var string $price     Price string.
 * @var bool   $required  Whether this choice requires customer input.
 * @var string $value     Option value slug.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcs-choice-details">
	<div class="wcs-choice-price-row">
		<label for="wcs_price"><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></label>
		<input type="text" name="wcs_price" id="wcs_price" value="<?php echo esc_attr( $price ); ?>" placeholder="0.00">
	</div>

	<p class="wcs-choice-required-row">
		<label class="wcs-choice-required">
			<input type="checkbox" name="wcs_required" value="1" <?php checked( $required ); ?>>
			<span><?php esc_html_e( 'Required choice', 'woo-spiegelloft-configurator' ); ?></span>
		</label>
	</p>

	<details class="wcs-advanced">
		<summary><?php esc_html_e( 'Advanced settings', 'woo-spiegelloft-configurator' ); ?></summary>
		<p class="wcs-field">
			<label for="wcs_option_value"><?php esc_html_e( 'Internal value slug', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="text" class="widefat" name="wcs_option_value" id="wcs_option_value" value="<?php echo esc_attr( $value ); ?>">
			<span class="description"><?php esc_html_e( 'Autofills from the title when left empty.', 'woo-spiegelloft-configurator' ); ?></span>
		</p>
		<p class="wcs-field">
			<label for="wcs_legacy_id"><?php esc_html_e( 'Legacy option ID', 'woo-spiegelloft-configurator' ); ?></label>
			<input type="number" class="widefat" name="wcs_legacy_id" id="wcs_legacy_id" value="<?php echo esc_attr( (string) $legacy_id ); ?>" min="0">
		</p>
	</details>
</div>
