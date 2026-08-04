<?php
/**
 * Product configurator tab template.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var int                              $template_id Assigned template ID.
 * @var array<int, array<string, mixed>> $templates   Available templates.
 * @var bool                             $enabled     Configurator enabled flag.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="wcs_configurator_product_data" class="panel woocommerce_options_panel hidden">
	<div class="options_group">
		<?php
		woocommerce_wp_checkbox(
			array(
				'id'          => '_wcs_configurator_enabled',
				'label'       => __( 'Enable configurator', 'woo-spiegelloft-configurator' ),
				'value'       => $enabled ? 'yes' : 'no',
				'description' => __( 'Enable the mirror configurator for this product.', 'woo-spiegelloft-configurator' ),
			)
		);
		?>
	</div>

	<div class="options_group">
		<p class="form-field">
			<label for="_wcs_template_id"><?php esc_html_e( 'Configurator template', 'woo-spiegelloft-configurator' ); ?></label>
			<select name="_wcs_template_id" id="_wcs_template_id" class="select short">
				<option value="0"><?php esc_html_e( '— Select template —', 'woo-spiegelloft-configurator' ); ?></option>
				<?php foreach ( $templates as $template ) : ?>
					<option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( $template_id, (int) $template['id'] ); ?>>
						<?php echo esc_html( (string) $template['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
	</div>
</div>