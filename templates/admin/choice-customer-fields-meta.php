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
				: array( array( 'label' => '', 'value' => '', 'price' => '', 'image' => '' ) );
			$field_name    = $base_name . '[' . $field_index . ']';
			?>
			<div class="wcs-customer-field-row" data-field-index="<?php echo esc_attr( (string) $field_index ); ?>">
				<div class="wcs-customer-field-box">
					<div class="wcs-customer-field-grid">
					<input type="text" class="wcs-customer-field-label" name="<?php echo esc_attr( $field_name ); ?>[label]" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'woo-spiegelloft-configurator' ); ?>">
					<select class="wcs-customer-field-type" name="<?php echo esc_attr( $field_name ); ?>[type]">
						<option value="dropdown" <?php selected( $field_type, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="text" <?php selected( $field_type, 'text' ); ?>><?php esc_html_e( 'Text / number input', 'woo-spiegelloft-configurator' ); ?></option>
					</select>
					<input type="text" class="wcs-customer-field-placeholder" name="<?php echo esc_attr( $field_name ); ?>[placeholder]" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'woo-spiegelloft-configurator' ); ?>">
					<div class="wcs-customer-field-meta">
						<input type="text" class="wcs-customer-field-price" name="<?php echo esc_attr( $field_name ); ?>[price]" value="<?php echo esc_attr( (string) ( $field['price'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Price', 'woo-spiegelloft-configurator' ); ?>">
					</div>
				</div>
				<div class="wcs-customer-field-options">
					<div class="wcs-customer-field-option-head" aria-hidden="true">
						<span><?php esc_html_e( 'Nested', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Label', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Image', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></span>
						<span><?php esc_html_e( 'Actions', 'woo-spiegelloft-configurator' ); ?></span>
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
							<div class="wcs-customer-field-option-image wcs-image-field">
								<input type="hidden" class="wcs-image-url" name="<?php echo esc_attr( $option_name ); ?>[image]" value="<?php echo esc_attr( (string) ( $field_option['image'] ?? '' ) ); ?>">
								<button type="button" class="button wcs-upload-image wcs-image-square <?php echo ! empty( $field_option['image'] ) ? 'has-image' : ''; ?>" aria-label="<?php esc_attr_e( 'Choose image', 'woo-spiegelloft-configurator' ); ?>">
									<?php if ( ! empty( $field_option['image'] ) ) : ?>
										<img src="<?php echo esc_url( (string) $field_option['image'] ); ?>" alt="">
									<?php else : ?>
										<span aria-hidden="true">+</span>
									<?php endif; ?>
								</button>
								<button type="button" class="button wcs-remove-image" aria-label="<?php esc_attr_e( 'Remove image', 'woo-spiegelloft-configurator' ); ?>" <?php echo empty( $field_option['image'] ) ? 'hidden' : ''; ?>>×</button>
							</div>
							<input type="text" class="wcs-customer-field-option-price" name="<?php echo esc_attr( $option_name ); ?>[price]" value="<?php echo esc_attr( (string) ( $field_option['price'] ?? '' ) ); ?>" placeholder="0.00">
							<div class="wcs-customer-option-actions">
								<button type="button" class="button wcs-icon-button wcs-customer-option-add" aria-label="<?php esc_attr_e( 'Add option', 'woo-spiegelloft-configurator' ); ?>" title="<?php esc_attr_e( 'Add option', 'woo-spiegelloft-configurator' ); ?>">
									<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								</button>
								<button type="button" class="button wcs-icon-button wcs-customer-option-remove" aria-label="<?php esc_attr_e( 'Remove option', 'woo-spiegelloft-configurator' ); ?>" title="<?php esc_attr_e( 'Remove option', 'woo-spiegelloft-configurator' ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</button>
							</div>
							<div class="wcs-customer-option-position-fields">
								<div class="wcs-customer-field-list">
									<?php wcs_render_customer_field_rows( $nested_fields, $option_name . '[customer_fields]', $depth + 1 ); ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				</div>
				<div class="wcs-customer-field-actions">
					<button type="button" class="button wcs-icon-button wcs-customer-field-add" aria-label="<?php esc_attr_e( 'Add field', 'woo-spiegelloft-configurator' ); ?>" title="<?php esc_attr_e( 'Add field', 'woo-spiegelloft-configurator' ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					</button>
					<button type="button" class="button wcs-icon-button wcs-customer-field-remove" aria-label="<?php esc_attr_e( 'Remove field', 'woo-spiegelloft-configurator' ); ?>" title="<?php esc_attr_e( 'Remove field', 'woo-spiegelloft-configurator' ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					</button>
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
		'price_enabled' => true,
		'placeholder'   => '',
		'options'       => array( array( 'label' => '', 'value' => '', 'price' => '', 'image' => '' ) ),
	),
);
?>
<div class="wcs-customer-fields">
	<div class="wcs-customer-field-list">
		<?php wcs_render_customer_field_rows( $field_rows, 'wcs_customer_fields' ); ?>
	</div>
</div>
