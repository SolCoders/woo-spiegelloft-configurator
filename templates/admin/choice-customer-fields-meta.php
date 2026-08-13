<?php
/**
 * Choice customer fields meta box template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<int, array<string, mixed>> $customer_fields Conditional customer field rows.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wcs_render_customer_field_rows' ) ) {
	/**
	 * Render customer field rows recursively.
	 *
	 * @param array<int, array<string, mixed>> $field_rows Field rows.
	 * @param string                           $base_name  Input name prefix.
	 * @param int                              $depth      Current nested depth.
	 */
	function wcs_render_customer_field_rows( array $field_rows, string $base_name, int $depth = 0 ): void {
		if ( $depth > 8 ) {
			return;
		}

		foreach ( $field_rows as $field_index => $field ) :
			$field_type    = 'text' === (string) ( $field['type'] ?? 'dropdown' ) ? 'text' : 'dropdown';
			$field_options = ! empty( $field['options'] ) && is_array( $field['options'] )
				? (array) $field['options']
				: array( array( 'label' => '', 'value' => '', 'price' => '' ) );
			$field_name    = $base_name . '[' . $field_index . ']';
			?>
			<div class="wcs-customer-field-row" data-field-index="<?php echo esc_attr( (string) $field_index ); ?>">
				<div class="wcs-customer-field-grid">
					<input type="text" class="wcs-customer-field-label" name="<?php echo esc_attr( $field_name ); ?>[label]" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'woo-spiegelloft-configurator' ); ?>">
					<select class="wcs-customer-field-type" name="<?php echo esc_attr( $field_name ); ?>[type]">
						<option value="dropdown" <?php selected( $field_type, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="text" <?php selected( $field_type, 'text' ); ?>><?php esc_html_e( 'Text / number input', 'woo-spiegelloft-configurator' ); ?></option>
					</select>
					<input type="text" class="wcs-customer-field-key" name="<?php echo esc_attr( $field_name ); ?>[key]" value="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'internal-key', 'woo-spiegelloft-configurator' ); ?>">
					<input type="text" class="wcs-customer-field-placeholder" name="<?php echo esc_attr( $field_name ); ?>[placeholder]" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'woo-spiegelloft-configurator' ); ?>">
					<input type="text" class="wcs-customer-field-price" name="<?php echo esc_attr( $field_name ); ?>[price]" value="<?php echo esc_attr( (string) ( $field['price'] ?? '' ) ); ?>" placeholder="0.00">
					<label><input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php esc_html_e( 'Required', 'woo-spiegelloft-configurator' ); ?></label>
					<label class="wcs-customer-field-price-toggle"><input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[price_enabled]" value="1" <?php checked( ! empty( $field['price_enabled'] ) ); ?>> <?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></label>
					<button type="button" class="button wcs-customer-field-add"><?php esc_html_e( 'Add field', 'woo-spiegelloft-configurator' ); ?></button>
					<button type="button" class="button wcs-customer-field-remove"><?php esc_html_e( 'Remove field', 'woo-spiegelloft-configurator' ); ?></button>
				</div>
				<div class="wcs-customer-field-options">
					<div class="wcs-customer-field-option-head" aria-hidden="true">
						<span><?php esc_html_e( 'Nested', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Label', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Add', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></span>
					</div>
					<?php foreach ( $field_options as $option_index => $field_option ) : ?>
						<?php
						$option_name   = $field_name . '[options][' . $option_index . ']';
						$nested_enabled = ! empty( $field_option['nested_enabled'] ) || ! empty( $field_option['position_enabled'] );
						$nested_fields  = $nested_enabled && ! empty( $field_option['customer_fields'] ) && is_array( $field_option['customer_fields'] )
							? (array) $field_option['customer_fields']
							: array();
						?>
						<div class="wcs-customer-field-option <?php echo $nested_enabled ? 'has-nested-fields' : ''; ?>">
							<div class="wcs-customer-option-position <?php echo $nested_enabled ? 'is-enabled' : ''; ?>">
								<label class="wcs-customer-option-position-switch">
									<input type="checkbox" class="wcs-customer-option-position-toggle" name="<?php echo esc_attr( $option_name ); ?>[nested_enabled]" value="1" <?php checked( $nested_enabled ); ?>>
									<span class="screen-reader-text"><?php esc_html_e( 'Nested fields', 'woo-spiegelloft-configurator' ); ?></span>
								</label>
							</div>
							<input type="text" class="wcs-customer-field-option-label" name="<?php echo esc_attr( $option_name ); ?>[label]" value="<?php echo esc_attr( (string) ( $field_option['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Option label', 'woo-spiegelloft-configurator' ); ?>">
							<input type="text" class="wcs-customer-field-option-value" name="<?php echo esc_attr( $option_name ); ?>[value]" value="<?php echo esc_attr( (string) ( $field_option['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'option-value', 'woo-spiegelloft-configurator' ); ?>">
							<input type="text" class="wcs-customer-field-option-price" name="<?php echo esc_attr( $option_name ); ?>[price]" value="<?php echo esc_attr( (string) ( $field_option['price'] ?? '' ) ); ?>" placeholder="0.00">
							<button type="button" class="button wcs-customer-option-add" aria-label="<?php esc_attr_e( 'Add value', 'woo-spiegelloft-configurator' ); ?>">+</button>
							<button type="button" class="button wcs-customer-option-remove" aria-label="<?php esc_attr_e( 'Remove value', 'woo-spiegelloft-configurator' ); ?>">-</button>
							<div class="wcs-customer-option-position-fields">
								<div class="wcs-customer-field-list">
									<?php wcs_render_customer_field_rows( $nested_fields, $option_name . '[customer_fields]', $depth + 1 ); ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		endforeach;
	}
}

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
?>
<div class="wcs-customer-fields">
	<div class="wcs-customer-fields__head">
		<p><?php esc_html_e( 'Show extra fields on the storefront only when this choice is selected.', 'woo-spiegelloft-configurator' ); ?></p>
		<button type="button" class="button wcs-customer-field-add"><?php esc_html_e( 'Add field', 'woo-spiegelloft-configurator' ); ?></button>
	</div>
	<div class="wcs-customer-field-list">
		<?php wcs_render_customer_field_rows( $field_rows, 'wcs_customer_fields' ); ?>
	</div>
</div>
