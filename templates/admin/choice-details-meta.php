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
 * @var array<int, array<string, mixed>>   $customer_fields Conditional customer field rows.
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

	<div class="wcs-field wcs-customer-fields">
		<div class="wcs-customer-fields__head">
			<div>
				<strong><?php esc_html_e( 'Customer fields', 'woo-spiegelloft-configurator' ); ?></strong>
				<p><?php esc_html_e( 'Show extra fields on the storefront only when this choice is selected.', 'woo-spiegelloft-configurator' ); ?></p>
			</div>
			<button type="button" class="button wcs-customer-field-add"><?php esc_html_e( 'Add field', 'woo-spiegelloft-configurator' ); ?></button>
		</div>
		<div class="wcs-customer-field-list">
			<?php
			$field_rows = ! empty( $customer_fields ) ? $customer_fields : array(
				array(
					'label'         => '',
					'key'           => '',
					'type'          => 'dropdown',
					'required'      => false,
					'price_enabled' => false,
					'placeholder'   => '',
					'options'       => array( array( 'label' => '', 'value' => '', 'price' => '' ) ),
				),
			);
			foreach ( $field_rows as $field_index => $field ) :
				$field_type    = 'text' === (string) ( $field['type'] ?? 'dropdown' ) ? 'text' : 'dropdown';
				$field_options = ! empty( $field['options'] ) && is_array( $field['options'] )
					? (array) $field['options']
					: array( array( 'label' => '', 'value' => '', 'price' => '' ) );
				?>
				<div class="wcs-customer-field-row" data-field-index="<?php echo esc_attr( (string) $field_index ); ?>">
					<div class="wcs-customer-field-grid">
						<input type="text" class="wcs-customer-field-label" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][label]" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'woo-spiegelloft-configurator' ); ?>">
						<select class="wcs-customer-field-type" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][type]">
							<option value="dropdown" <?php selected( $field_type, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="text" <?php selected( $field_type, 'text' ); ?>><?php esc_html_e( 'Text / number input', 'woo-spiegelloft-configurator' ); ?></option>
						</select>
						<input type="text" class="wcs-customer-field-key" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][key]" value="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'internal-key', 'woo-spiegelloft-configurator' ); ?>">
						<input type="text" class="wcs-customer-field-placeholder" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][placeholder]" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'woo-spiegelloft-configurator' ); ?>">
						<label><input type="checkbox" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php esc_html_e( 'Required', 'woo-spiegelloft-configurator' ); ?></label>
						<label class="wcs-customer-field-price-toggle"><input type="checkbox" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][price_enabled]" value="1" <?php checked( ! empty( $field['price_enabled'] ) ); ?>> <?php esc_html_e( 'Prices on dropdown values', 'woo-spiegelloft-configurator' ); ?></label>
						<button type="button" class="button wcs-customer-field-remove"><?php esc_html_e( 'Remove field', 'woo-spiegelloft-configurator' ); ?></button>
					</div>
					<div class="wcs-customer-field-options">
						<div class="wcs-inline-position-options-title"><?php esc_html_e( 'Dropdown values', 'woo-spiegelloft-configurator' ); ?></div>
						<?php foreach ( $field_options as $option_index => $field_option ) : ?>
							<div class="wcs-customer-field-option">
								<input type="text" class="wcs-customer-field-option-label" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][label]" value="<?php echo esc_attr( (string) ( $field_option['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'top center', 'woo-spiegelloft-configurator' ); ?>">
								<input type="text" class="wcs-customer-field-option-value" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][value]" value="<?php echo esc_attr( (string) ( $field_option['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'top-center', 'woo-spiegelloft-configurator' ); ?>">
								<input type="text" class="wcs-customer-field-option-price" name="wcs_customer_fields[<?php echo esc_attr( (string) $field_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][price]" value="<?php echo esc_attr( (string) ( $field_option['price'] ?? '' ) ); ?>" placeholder="0.00">
								<button type="button" class="button wcs-customer-option-add" aria-label="<?php esc_attr_e( 'Add value', 'woo-spiegelloft-configurator' ); ?>">+</button>
								<button type="button" class="button wcs-customer-option-remove" aria-label="<?php esc_attr_e( 'Remove value', 'woo-spiegelloft-configurator' ); ?>">-</button>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
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
