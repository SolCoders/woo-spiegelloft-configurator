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
			'when'         => array(),
			'when_field'   => 'value',
			'when_operator' => 'equals',
			'then'         => 'require',
			'target'       => '',
			'target_value' => '',
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
			$field = (string) ( $rule['when_field'] ?? 'value' );
			$operator = (string) ( $rule['when_operator'] ?? 'equals' );
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
					<?php esc_html_e( 'Compare', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_field]">
						<option value="value" <?php selected( $field, 'value' ); ?>><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="price" <?php selected( $field, 'price' ); ?>><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="text" <?php selected( $field, 'text' ); ?>><?php esc_html_e( 'Text', 'woo-spiegelloft-configurator' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Condition', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_operator]">
						<option value="equals" <?php selected( $operator, 'equals' ); ?>><?php esc_html_e( 'equals', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="not_equals" <?php selected( $operator, 'not_equals' ); ?>><?php esc_html_e( 'does not equal', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="greater_than" <?php selected( $operator, 'greater_than' ); ?>><?php esc_html_e( 'greater than', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="less_than" <?php selected( $operator, 'less_than' ); ?>><?php esc_html_e( 'less than', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="selected" <?php selected( $operator, 'selected' ); ?>><?php esc_html_e( 'is selected', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="empty" <?php selected( $operator, 'empty' ); ?>><?php esc_html_e( 'is empty', 'woo-spiegelloft-configurator' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?>
					<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_value]" value="<?php echo esc_attr( $when_v ); ?>" placeholder="option-value">
				</label>
				<label>
					<?php esc_html_e( 'Then', 'woo-spiegelloft-configurator' ); ?>
					<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][then]">
						<option value="require" <?php selected( (string) ( $rule['then'] ?? '' ), 'require' ); ?>><?php esc_html_e( 'Require', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="disallow" <?php selected( (string) ( $rule['then'] ?? '' ), 'disallow' ); ?>><?php esc_html_e( 'Block', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="require_value" <?php selected( (string) ( $rule['then'] ?? '' ), 'require_value' ); ?>><?php esc_html_e( 'Require option', 'woo-spiegelloft-configurator' ); ?></option>
						<option value="disallow_value" <?php selected( (string) ( $rule['then'] ?? '' ), 'disallow_value' ); ?>><?php esc_html_e( 'Block option', 'woo-spiegelloft-configurator' ); ?></option>
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
				<label>
					<?php esc_html_e( 'Target value', 'woo-spiegelloft-configurator' ); ?>
					<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][target_value]" value="<?php echo esc_attr( (string) ( $rule['target_value'] ?? '' ) ); ?>" placeholder="option-value">
				</label>
				<button type="button" class="button-link wcs-remove-rule"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
			</div>
		<?php endforeach; ?>
	</div>

	<button type="button" class="button" id="wcs-add-rule"><?php esc_html_e( 'Add rule', 'woo-spiegelloft-configurator' ); ?></button>
</div>
