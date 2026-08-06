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
 * @var string                             $image       Image URL.
 * @var string                             $value       Option value slug.
 * @var bool                               $position_enabled Whether position choices are enabled.
 * @var string                             $position_label Position field label.
 * @var array<int, array<string, string>>  $position_options Position option rows.
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

	<div class="wcs-field wcs-image-field">
		<label for="wcs_image"><strong><?php esc_html_e( 'Image', 'woo-spiegelloft-configurator' ); ?></strong></label>
		<div class="wcs-image-picker">
			<input type="text" class="widefat wcs-image-url" name="wcs_image" id="wcs_image" value="<?php echo esc_attr( $image ); ?>">
			<button type="button" class="button wcs-upload-image"><?php esc_html_e( 'Choose image', 'woo-spiegelloft-configurator' ); ?></button>
			<div class="wcs-image-preview">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="">
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="wcs-field wcs-position-settings">
		<label class="wcs-position-toggle">
			<input type="checkbox" name="wcs_position_enabled" value="1" <?php checked( $position_enabled ); ?>>
			<strong><?php esc_html_e( 'Enable position choices', 'woo-spiegelloft-configurator' ); ?></strong>
		</label>
		<div class="wcs-position-fields" <?php echo $position_enabled ? '' : 'hidden'; ?>>
			<label class="wcs-position-label">
				<span><?php esc_html_e( 'Position label', 'woo-spiegelloft-configurator' ); ?></span>
				<input type="text" class="regular-text" name="wcs_position_label" value="<?php echo esc_attr( $position_label ); ?>" placeholder="<?php esc_attr_e( 'Position of this choice', 'woo-spiegelloft-configurator' ); ?>">
			</label>
			<div class="wcs-position-options">
				<?php
				$rows = ! empty( $position_options ) ? $position_options : array( array( 'label' => '', 'value' => '' ) );
				foreach ( $rows as $index => $row ) :
					$row_label = (string) ( $row['label'] ?? '' );
					$row_value = (string) ( $row['value'] ?? '' );
					?>
					<div class="wcs-position-row">
						<button type="button" class="button wcs-position-remove" aria-label="<?php esc_attr_e( 'Remove position', 'woo-spiegelloft-configurator' ); ?>">-</button>
						<input type="text" name="wcs_position_options[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $row_label ); ?>" placeholder="<?php esc_attr_e( 'top center', 'woo-spiegelloft-configurator' ); ?>">
						<input type="text" name="wcs_position_options[<?php echo esc_attr( (string) $index ); ?>][value]" value="<?php echo esc_attr( $row_value ); ?>" placeholder="<?php esc_attr_e( 'top-center', 'woo-spiegelloft-configurator' ); ?>">
						<button type="button" class="button wcs-position-add" aria-label="<?php esc_attr_e( 'Add position', 'woo-spiegelloft-configurator' ); ?>">+</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

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
