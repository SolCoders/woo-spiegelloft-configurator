<?php
/**
 * Template restrictions rule builder meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<int, array<string,mixed>>      $rules  Validation rules.
 * @var array<string, array<string,mixed>>   $groups Group definitions.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $rules ) ) {
	$rules = array(
		array(
			'when'   => array(),
			'then'   => 'require',
			'target' => '',
		),
	);
}
?>
<div class="wcs-rule-builder">
	<p class="description"><?php esc_html_e( 'Add rules like: when the customer picks X, then require Y.', 'woo-spiegelloft-configurator' ); ?></p>

	<div id="wcs-rules-list">
		<?php foreach ( $rules as $index => $rule ) : ?>
			<?php
			$when   = (array) ( $rule['when'] ?? array() );
			$when_k = (string) ( array_key_first( $when ) ?: '' );
			$when_v = (string) ( $when[ $when_k ] ?? '' );
			?>
			<div class="wcs-rule-row">
				<label>
					<?php esc_html_e( 'When', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_group]" class="wcs-rule-when-group">
						<option value=""><?php esc_html_e( '— Category —', 'woo-spiegelloft-configurator' ); ?></option>
						<?php foreach ( $groups as $slug => $group ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $when_k, $slug ); ?>>
								<?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'equals', 'woo-spiegelloft-configurator' ); ?>
					<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_value]" value="<?php echo esc_attr( $when_v ); ?>" placeholder="option-value">
				</label>
				<label>
					<?php esc_html_e( 'Then', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][then]">
						<option value="require" <?php selected( (string) ( $rule['then'] ?? '' ), 'require' ); ?>><?php esc_html_e( 'Require', 'woo-spiegelloft-configurator' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Target category', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][target]">
						<option value=""><?php esc_html_e( '— Category —', 'woo-spiegelloft-configurator' ); ?></option>
						<?php foreach ( $groups as $slug => $group ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) ( $rule['target'] ?? '' ), $slug ); ?>>
								<?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="button" class="button-link wcs-remove-rule"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
			</div>
		<?php endforeach; ?>
	</div>

	<button type="button" class="button" id="wcs-add-rule"><?php esc_html_e( 'Add rule', 'woo-spiegelloft-configurator' ); ?></button>
</div>
