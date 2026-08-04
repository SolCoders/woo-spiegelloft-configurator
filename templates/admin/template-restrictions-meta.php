<?php
/**
 * Template restrictions rule builder meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<int, array<string,mixed>>     $rules  Validation rules.
 * @var array<string, array<string,mixed>>  $groups Group definitions.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $rules ) ) {
	$rules = array(
		array(
			'when'          => array(),
			'when_source'   => 'category',
			'when_path'     => '',
			'when_field'    => 'value',
			'when_operator' => 'equals',
			'rule_type'     => 'required',
			'then'          => 'require',
			'target_type'   => 'category',
			'target'        => '',
			'target_value'  => '',
			'min'           => '',
			'max'           => '',
			'message'       => '',
			'error_seconds' => 4,
			'restore'       => false,
		),
	);
}
?>
<div class="wcs-rule-builder">
	<p class="description"><?php esc_html_e( 'Create template-scoped validation and storefront behavior rules. Start with the rule type, then fill only the visible fields.', 'woo-spiegelloft-configurator' ); ?></p>

	<div id="wcs-rules-list">
		<?php foreach ( $rules as $index => $rule ) : ?>
			<?php
			$when        = (array) ( $rule['when'] ?? array() );
			$when_k      = (string) ( $rule['when_path'] ?? array_key_first( $when ) ?: '' );
			$when_v      = (string) ( $when[ $when_k ] ?? '' );
			$source      = (string) ( $rule['when_source'] ?? 'category' );
			$field       = (string) ( $rule['when_field'] ?? 'value' );
			$operator    = (string) ( $rule['when_operator'] ?? 'equals' );
			$rule_type   = (string) ( $rule['rule_type'] ?? 'required' );
			$target_type = (string) ( $rule['target_type'] ?? 'category' );
			$then        = (string) ( $rule['then'] ?? 'require' );
			?>
			<div class="wcs-rule-row" data-rule-type="<?php echo esc_attr( $rule_type ); ?>" data-then="<?php echo esc_attr( $then ); ?>" data-source="<?php echo esc_attr( $source ); ?>">
				<div class="wcs-rule-row-header">
					<div>
						<strong><?php esc_html_e( 'Restriction rule', 'woo-spiegelloft-configurator' ); ?></strong>
						<span><?php esc_html_e( 'Define when it applies and what should happen.', 'woo-spiegelloft-configurator' ); ?></span>
					</div>
					<button type="button" class="button-link wcs-remove-rule"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
				</div>

				<div class="wcs-rule-section wcs-rule-section--type">
					<label class="wcs-rule-field">
						<span><?php esc_html_e( 'Rule type', 'woo-spiegelloft-configurator' ); ?></span>
						<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][rule_type]" class="wcs-rule-type">
							<option value="required" <?php selected( $rule_type, 'required' ); ?>><?php esc_html_e( 'Required field', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="block" <?php selected( $rule_type, 'block' ); ?>><?php esc_html_e( 'Block option/category', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="visibility" <?php selected( $rule_type, 'visibility' ); ?>><?php esc_html_e( 'Show/hide field', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="clear" <?php selected( $rule_type, 'clear' ); ?>><?php esc_html_e( 'Clear dependent value', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="range" <?php selected( $rule_type, 'range' ); ?>><?php esc_html_e( 'Numeric range', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="disable" <?php selected( $rule_type, 'disable' ); ?>><?php esc_html_e( 'Disable option', 'woo-spiegelloft-configurator' ); ?></option>
						</select>
					</label>
					<p class="wcs-rule-summary"></p>
				</div>

				<div class="wcs-rule-section">
					<h4><?php esc_html_e( 'When this is true', 'woo-spiegelloft-configurator' ); ?></h4>
					<div class="wcs-rule-grid">
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Source', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_source]" class="wcs-rule-source">
								<option value="category" <?php selected( $source, 'category' ); ?>><?php esc_html_e( 'Category', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="nested" <?php selected( $source, 'nested' ); ?>><?php esc_html_e( 'Nested field', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="dimension" <?php selected( $source, 'dimension' ); ?>><?php esc_html_e( 'Dimension field', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
						</label>
						<label class="wcs-rule-field wcs-rule-category-field" data-visible-for-source="category">
							<span><?php esc_html_e( 'Category', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_group]" class="wcs-rule-when-group">
								<option value=""><?php esc_html_e( '-- Category --', 'woo-spiegelloft-configurator' ); ?></option>
								<?php foreach ( $groups as $slug => $group ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $when_k, $slug ); ?>>
										<?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="wcs-rule-field" data-visible-for-source="nested dimension">
							<span><?php esc_html_e( 'Path', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_path]" value="<?php echo esc_attr( $when_k ); ?>" placeholder="makeup_mirror.position or width">
						</label>
						<label class="wcs-rule-field" data-visible-for-operator="equals not_equals greater_than less_than">
							<span><?php esc_html_e( 'Compare', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_field]">
								<option value="value" <?php selected( $field, 'value' ); ?>><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="price" <?php selected( $field, 'price' ); ?>><?php esc_html_e( 'Price', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="text" <?php selected( $field, 'text' ); ?>><?php esc_html_e( 'Text', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
						</label>
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Condition', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_operator]" class="wcs-rule-operator">
								<option value="equals" <?php selected( $operator, 'equals' ); ?>><?php esc_html_e( 'equals', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="not_equals" <?php selected( $operator, 'not_equals' ); ?>><?php esc_html_e( 'does not equal', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="greater_than" <?php selected( $operator, 'greater_than' ); ?>><?php esc_html_e( 'greater than', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="less_than" <?php selected( $operator, 'less_than' ); ?>><?php esc_html_e( 'less than', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="selected" <?php selected( $operator, 'selected' ); ?>><?php esc_html_e( 'is selected', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="empty" <?php selected( $operator, 'empty' ); ?>><?php esc_html_e( 'is empty', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
						</label>
						<label class="wcs-rule-field" data-visible-for-operator="equals not_equals greater_than less_than">
							<span><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][when_value]" value="<?php echo esc_attr( $when_v ); ?>" placeholder="option-value">
						</label>
					</div>
				</div>

				<div class="wcs-rule-section">
					<h4><?php esc_html_e( 'Do this', 'woo-spiegelloft-configurator' ); ?></h4>
					<div class="wcs-rule-grid">
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Action', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][then]" class="wcs-rule-action">
								<option value="require" <?php selected( $then, 'require' ); ?>><?php esc_html_e( 'Require', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="disallow" <?php selected( $then, 'disallow' ); ?>><?php esc_html_e( 'Block', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="require_value" <?php selected( $then, 'require_value' ); ?>><?php esc_html_e( 'Require option', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="disallow_value" <?php selected( $then, 'disallow_value' ); ?>><?php esc_html_e( 'Block option', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="show" <?php selected( $then, 'show' ); ?>><?php esc_html_e( 'Show', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="hide" <?php selected( $then, 'hide' ); ?>><?php esc_html_e( 'Hide', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="clear" <?php selected( $then, 'clear' ); ?>><?php esc_html_e( 'Clear', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="disable_option" <?php selected( $then, 'disable_option' ); ?>><?php esc_html_e( 'Disable option', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="validate_range" <?php selected( $then, 'validate_range' ); ?>><?php esc_html_e( 'Validate range', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
						</label>
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Target type', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][target_type]">
								<option value="category" <?php selected( $target_type, 'category' ); ?>><?php esc_html_e( 'Category', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="nested" <?php selected( $target_type, 'nested' ); ?>><?php esc_html_e( 'Nested field', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="dimension" <?php selected( $target_type, 'dimension' ); ?>><?php esc_html_e( 'Dimension field', 'woo-spiegelloft-configurator' ); ?></option>
								<option value="option" <?php selected( $target_type, 'option' ); ?>><?php esc_html_e( 'Option', 'woo-spiegelloft-configurator' ); ?></option>
							</select>
						</label>
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Target', 'woo-spiegelloft-configurator' ); ?></span>
							<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][target]">
								<option value=""><?php esc_html_e( '-- Category --', 'woo-spiegelloft-configurator' ); ?></option>
								<?php foreach ( $groups as $slug => $group ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) ( $rule['target'] ?? '' ), $slug ); ?>>
										<?php echo esc_html( (string) ( $group['label'] ?? $slug ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="wcs-rule-field" data-visible-for-action="require_value disallow_value show hide clear disable_option validate_range">
							<span><?php esc_html_e( 'Target value/path', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][target_value]" value="<?php echo esc_attr( (string) ( $rule['target_value'] ?? '' ) ); ?>" placeholder="option-value or group.field">
						</label>
					</div>
				</div>

				<div class="wcs-rule-section wcs-rule-section--range" data-visible-for-action="validate_range">
					<h4><?php esc_html_e( 'Range and error behavior', 'woo-spiegelloft-configurator' ); ?></h4>
					<div class="wcs-rule-grid">
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Min formula', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][min]" value="<?php echo esc_attr( (string) ( $rule['min'] ?? '' ) ); ?>" placeholder="100">
						</label>
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Max formula', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][max]" value="<?php echo esc_attr( (string) ( $rule['max'] ?? '' ) ); ?>" placeholder="width - 100">
						</label>
						<label class="wcs-rule-field wcs-rule-field--wide">
							<span><?php esc_html_e( 'Error message', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][message]" value="<?php echo esc_attr( (string) ( $rule['message'] ?? '' ) ); ?>">
						</label>
						<label class="wcs-rule-field">
							<span><?php esc_html_e( 'Error seconds', 'woo-spiegelloft-configurator' ); ?></span>
							<input type="number" min="0" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][error_seconds]" value="<?php echo esc_attr( (string) (int) ( $rule['error_seconds'] ?? 4 ) ); ?>">
						</label>
						<label class="wcs-rule-checkbox">
							<input type="checkbox" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][restore]" value="1" <?php checked( ! empty( $rule['restore'] ) ); ?>>
							<?php esc_html_e( 'Restore previous valid value', 'woo-spiegelloft-configurator' ); ?>
						</label>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<button type="button" class="button" id="wcs-add-rule"><?php esc_html_e( 'Add rule', 'woo-spiegelloft-configurator' ); ?></button>
</div>
