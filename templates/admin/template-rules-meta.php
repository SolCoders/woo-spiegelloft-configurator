<?php
/**
 * Template rules meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<int, array<string, mixed>> $rules Validation rules.
 */

defined( 'ABSPATH' ) || exit;

$rules_json = wp_json_encode( $rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
?>
<p class="description">
	<?php esc_html_e( 'Define conditional validation rules as JSON. Example: [{"when":{"material":"premium"},"then":"require","target":"sealing"}]', 'woo-spiegelloft-configurator' ); ?>
</p>
<textarea name="wcs_template_rules" id="wcs_template_rules" rows="10" class="large-text code wcs-rules-editor"><?php echo esc_textarea( (string) $rules_json ); ?></textarea>