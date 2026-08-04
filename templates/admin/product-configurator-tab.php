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
	<div class="options_group wcs-product-configurator-intro">
		<p class="form-field">
			<?php esc_html_e( 'Turn on the mirror customizer for this product and choose which template controls the available choices and size limits.', 'woo-spiegelloft-configurator' ); ?>
		</p>
	</div>

	<div class="options_group">
		<?php
		woocommerce_wp_checkbox(
			array(
				'id'          => '_wcs_enabled',
				'label'       => __( 'Enable mirror customizer', 'woo-spiegelloft-configurator' ),
				'value'       => $enabled ? 'yes' : 'no',
				'description' => __( 'Customers can configure this product on the storefront.', 'woo-spiegelloft-configurator' ),
			)
		);
		?>
	</div>

	<div class="options_group">
		<p class="form-field">
			<label for="_wcs_template_id"><strong><?php esc_html_e( 'Mirror template', 'woo-spiegelloft-configurator' ); ?></strong></label>
			<select name="_wcs_template_id" id="_wcs_template_id" class="select short">
				<option value="0"><?php esc_html_e( '— Select template —', 'woo-spiegelloft-configurator' ); ?></option>
				<?php foreach ( $templates as $template ) : ?>
					<option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( $template_id, (int) $template['id'] ); ?>>
						<?php echo esc_html( (string) $template['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wcs_template' ) ); ?>"><?php esc_html_e( 'Manage templates', 'woo-spiegelloft-configurator' ); ?></a>
			</span>
		</p>
	</div>
</div>
