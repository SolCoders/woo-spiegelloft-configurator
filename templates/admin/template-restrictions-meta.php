<?php
/**
 * Template restrictions rule builder meta box.
 *
 * @package WooSpiegelloftConfigurator
 *
 * @var array<int, array<string,mixed>>     $rules  Validation rules.
 * @var array<string, array<string,mixed>>  $groups         Group definitions.
 * @var array<string, array<int,array<string,string>>> $option_choices         Template option choices.
 * @var array<string, string>                          $template_field_choices    Template choice field keys.
 * @var array<string, string>                          $measurement_field_choices Measurement field keys.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $rules ) ) {
	$rules = array(
		array(
			'rule_type'     => 'required_field',
			'match'         => 'all',
			'conditions'    => array(
				array(
					'source'   => 'category',
					'path'     => '',
					'field'    => 'value',
					'type'     => 'selection',
					'operator' => 'equals',
					'value'    => '',
				),
			),
			'actions'       => array(
				array(
					'action'       => 'require',
					'target_type'  => 'category',
					'target'       => '',
					'target_value' => '',
					'value'        => '',
				),
			),
			'message'       => '',
			'error_seconds' => 4,
			'restore'       => false,
		),
	);
}

$field_types = array(
	'selection' => __( 'Selection', 'woo-spiegelloft-configurator' ),
	'number'    => __( 'Number', 'woo-spiegelloft-configurator' ),
	'string'    => __( 'Text', 'woo-spiegelloft-configurator' ),
	'boolean'   => __( 'Yes/no', 'woo-spiegelloft-configurator' ),
);

$condition_operators = array(
	'equals'                => __( 'equals', 'woo-spiegelloft-configurator' ),
	'not_equals'            => __( 'does not equal', 'woo-spiegelloft-configurator' ),
	'contains'              => __( 'contains', 'woo-spiegelloft-configurator' ),
	'not_contains'          => __( 'does not contain', 'woo-spiegelloft-configurator' ),
	'is_empty'              => __( 'is empty', 'woo-spiegelloft-configurator' ),
	'is_not_empty'          => __( 'is not empty', 'woo-spiegelloft-configurator' ),
	'one_of'                => __( 'is one of', 'woo-spiegelloft-configurator' ),
	'not_one_of'            => __( 'is not one of', 'woo-spiegelloft-configurator' ),
	'greater_than'          => __( 'greater than', 'woo-spiegelloft-configurator' ),
	'greater_than_or_equal' => __( 'greater than or equal', 'woo-spiegelloft-configurator' ),
	'less_than'             => __( 'less than', 'woo-spiegelloft-configurator' ),
	'less_than_or_equal'    => __( 'less than or equal', 'woo-spiegelloft-configurator' ),
	'is_true'               => __( 'is true', 'woo-spiegelloft-configurator' ),
	'is_false'              => __( 'is false', 'woo-spiegelloft-configurator' ),
);

$action_options = array(
	'require'        => __( 'Require', 'woo-spiegelloft-configurator' ),
	'disallow'       => __( 'Block', 'woo-spiegelloft-configurator' ),
	'require_value'  => __( 'Require option', 'woo-spiegelloft-configurator' ),
	'disallow_value' => __( 'Block option', 'woo-spiegelloft-configurator' ),
	'show'           => __( 'Show', 'woo-spiegelloft-configurator' ),
	'hide'           => __( 'Hide', 'woo-spiegelloft-configurator' ),
	'enable'         => __( 'Enable', 'woo-spiegelloft-configurator' ),
	'disable'        => __( 'Disable', 'woo-spiegelloft-configurator' ),
	'clear'          => __( 'Clear', 'woo-spiegelloft-configurator' ),
	'set_min'        => __( 'Set minimum', 'woo-spiegelloft-configurator' ),
	'set_max'        => __( 'Set maximum', 'woo-spiegelloft-configurator' ),
	'validate_range' => __( 'Validate range', 'woo-spiegelloft-configurator' ),
);

if ( ! function_exists( 'wcs_rule_condition_rows' ) ) {
function wcs_rule_condition_rows( array $rule ): array {
	$conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
	if ( empty( $conditions ) ) {
		$when = (array) ( $rule['when'] ?? array() );
		$path = (string) ( $rule['when_path'] ?? array_key_first( $when ) ?: '' );
		$conditions[] = array(
			'source'   => (string) ( $rule['when_source'] ?? 'category' ),
			'path'     => $path,
			'field'    => (string) ( $rule['when_field'] ?? 'value' ),
			'type'     => ( 'dimension' === (string) ( $rule['when_source'] ?? '' ) ) ? 'number' : 'selection',
			'operator' => (string) ( $rule['when_operator'] ?? 'equals' ),
			'value'    => (string) ( $when[ $path ] ?? $rule['when_value'] ?? '' ),
		);
	}
	return array_values( $conditions );
}
}

if ( ! function_exists( 'wcs_rule_action_rows' ) ) {
function wcs_rule_action_rows( array $rule ): array {
	$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
	if ( empty( $actions ) ) {
		$actions[] = array(
			'action'       => (string) ( $rule['then'] ?? 'require' ),
			'target_type'  => (string) ( $rule['target_type'] ?? 'category' ),
			'target'       => (string) ( $rule['target'] ?? '' ),
			'target_value' => (string) ( $rule['target_value'] ?? '' ),
			'value'        => (string) ( $rule['max'] ?? '' ),
			'min'          => (string) ( $rule['min'] ?? '' ),
			'max'          => (string) ( $rule['max'] ?? '' ),
		);
	}
	return array_values( $actions );
}
}
?>
<div class="wcs-rule-builder">
	<p class="description"><?php esc_html_e( 'Create template rules with one clear trigger and one result. Add more rows only when needed.', 'woo-spiegelloft-configurator' ); ?></p>

	<div id="wcs-rules-list">
		<?php foreach ( $rules as $index => $rule ) : ?>
			<?php
			$conditions = wcs_rule_condition_rows( (array) $rule );
			$actions    = wcs_rule_action_rows( (array) $rule );
			$rule_type  = (string) ( $rule['rule_type'] ?? 'required_field' );
			$match      = (string) ( $rule['match'] ?? $rule['condition_match'] ?? 'all' );
			?>
			<div class="wcs-rule-row" data-rule-type="<?php echo esc_attr( $rule_type ); ?>">
				<div class="wcs-rule-row-header">
					<div>
						<strong><?php esc_html_e( 'Rule', 'woo-spiegelloft-configurator' ); ?></strong>
						<span><?php esc_html_e( 'When this matches, apply the result below.', 'woo-spiegelloft-configurator' ); ?></span>
					</div>
					<button type="button" class="button-link wcs-remove-rule"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
				</div>

				<div class="wcs-rule-section wcs-rule-section--type">
					<label class="wcs-rule-field">
						<span><?php esc_html_e( 'Rule type', 'woo-spiegelloft-configurator' ); ?></span>
						<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][rule_type]" class="wcs-rule-type">
							<option value="required_field" <?php selected( $rule_type, 'required_field' ); ?>><?php esc_html_e( 'Required field', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="constraint" <?php selected( $rule_type, 'constraint' ); ?>><?php esc_html_e( 'Computed constraint', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="availability" <?php selected( $rule_type, 'availability' ); ?>><?php esc_html_e( 'Enable/disable', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="visibility" <?php selected( $rule_type, 'visibility' ); ?>><?php esc_html_e( 'Show/hide', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="selection_rule" <?php selected( $rule_type, 'selection_rule' ); ?>><?php esc_html_e( 'Selection/text rule', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="block" <?php selected( $rule_type, 'block' ); ?>><?php esc_html_e( 'Block option', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="clear" <?php selected( $rule_type, 'clear' ); ?>><?php esc_html_e( 'Clear dependent value', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="range" <?php selected( $rule_type, 'range' ); ?>><?php esc_html_e( 'Numeric range', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="disable" <?php selected( $rule_type, 'disable' ); ?>><?php esc_html_e( 'Disable option', 'woo-spiegelloft-configurator' ); ?></option>
						</select>
					</label>
					<p class="wcs-rule-summary"></p>
					<label class="wcs-rule-field wcs-rule-match">
						<span><?php esc_html_e( 'Match', 'woo-spiegelloft-configurator' ); ?></span>
						<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][match]">
							<option value="all" <?php selected( $match, 'all' ); ?>><?php esc_html_e( 'All rows (AND)', 'woo-spiegelloft-configurator' ); ?></option>
							<option value="any" <?php selected( $match, 'any' ); ?>><?php esc_html_e( 'Any row (OR)', 'woo-spiegelloft-configurator' ); ?></option>
						</select>
					</label>
				</div>

				<div class="wcs-rule-section">
					<div class="wcs-rule-section-heading">
						<h4><?php esc_html_e( 'When', 'woo-spiegelloft-configurator' ); ?></h4>
					</div>
					<div class="wcs-rule-condition-list">
						<?php foreach ( $conditions as $condition_index => $condition ) : ?>
							<div class="wcs-rule-condition-row">
								<input type="hidden" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][source]" class="wcs-rule-source" value="<?php echo esc_attr( (string) ( $condition['source'] ?? 'category' ) ); ?>">
								<label class="wcs-rule-field wcs-rule-category-field">
									<span><?php esc_html_e( 'Field', 'woo-spiegelloft-configurator' ); ?></span>
									<select class="wcs-rule-when-group">
										<option value=""><?php esc_html_e( 'Choose field', 'woo-spiegelloft-configurator' ); ?></option>
										<optgroup label="<?php esc_attr_e( 'Measurements', 'woo-spiegelloft-configurator' ); ?>">
											<?php foreach ( $measurement_field_choices as $field_key => $field_label ) : ?>
												<option value="<?php echo esc_attr( $field_key ); ?>" data-source="dimension" data-type="number" <?php selected( (string) ( $condition['path'] ?? '' ), $field_key ); ?>>
													<?php echo esc_html( $field_label ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
										<?php if ( ! empty( $template_field_choices ) ) : ?>
											<optgroup label="<?php esc_attr_e( 'Choices', 'woo-spiegelloft-configurator' ); ?>">
												<?php foreach ( $template_field_choices as $field_key => $field_label ) : ?>
													<option value="<?php echo esc_attr( $field_key ); ?>" data-source="category" data-type="selection" <?php selected( (string) ( $condition['path'] ?? '' ), $field_key ); ?>>
														<?php echo esc_html( $field_label ); ?>
													</option>
												<?php endforeach; ?>
											</optgroup>
										<?php endif; ?>
									</select>
									<input type="hidden" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][path]" value="<?php echo esc_attr( (string) ( $condition['path'] ?? '' ) ); ?>">
								</label>
								<label class="wcs-rule-field">
									<span><?php esc_html_e( 'Type', 'woo-spiegelloft-configurator' ); ?></span>
									<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][type]" class="wcs-rule-value-type">
										<?php foreach ( $field_types as $type_key => $type_label ) : ?>
											<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( (string) ( $condition['type'] ?? 'selection' ), $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="wcs-rule-field">
									<span><?php esc_html_e( 'Condition', 'woo-spiegelloft-configurator' ); ?></span>
									<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][operator]" class="wcs-rule-operator">
										<?php foreach ( $condition_operators as $operator_key => $operator_label ) : ?>
											<option value="<?php echo esc_attr( $operator_key ); ?>" <?php selected( (string) ( $condition['operator'] ?? 'equals' ), $operator_key ); ?>><?php echo esc_html( $operator_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="wcs-rule-field wcs-rule-value-field">
									<span><?php esc_html_e( 'Value', 'woo-spiegelloft-configurator' ); ?></span>
									<select class="wcs-rule-option-value">
										<option value=""><?php esc_html_e( 'Choose option value', 'woo-spiegelloft-configurator' ); ?></option>
										<?php foreach ( $option_choices as $choice_group => $choices ) : ?>
											<optgroup label="<?php echo esc_attr( (string) ( $groups[ $choice_group ]['label'] ?? $choice_group ) ); ?>">
												<?php foreach ( $choices as $choice ) : ?>
													<option value="<?php echo esc_attr( (string) $choice['value'] ); ?>" <?php selected( (string) ( $condition['value'] ?? '' ), (string) $choice['value'] ); ?>>
														<?php echo esc_html( (string) $choice['label'] ); ?>
													</option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
									<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][value]" value="<?php echo esc_attr( (string) ( $condition['value'] ?? '' ) ); ?>" placeholder="500 or value-1,value-2">
								</label>
								<input type="hidden" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][conditions][<?php echo esc_attr( (string) $condition_index ); ?>][field]" value="<?php echo esc_attr( (string) ( $condition['field'] ?? 'value' ) ); ?>">
								<div class="wcs-rule-row-actions">
									<button type="button" class="button wcs-add-condition"><?php esc_html_e( 'Add', 'woo-spiegelloft-configurator' ); ?></button>
									<button type="button" class="button wcs-remove-condition"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wcs-rule-section">
					<h4><?php esc_html_e( 'Then', 'woo-spiegelloft-configurator' ); ?></h4>
					<div class="wcs-rule-action-list">
						<?php foreach ( $actions as $action_index => $action ) : ?>
							<div class="wcs-rule-action-row">
								<label class="wcs-rule-field">
									<span><?php esc_html_e( 'Action', 'woo-spiegelloft-configurator' ); ?></span>
									<select name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][actions][<?php echo esc_attr( (string) $action_index ); ?>][action]" class="wcs-rule-action">
										<?php foreach ( $action_options as $action_key => $action_label ) : ?>
											<option value="<?php echo esc_attr( $action_key ); ?>" <?php selected( (string) ( $action['action'] ?? 'require' ), $action_key ); ?>><?php echo esc_html( $action_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<input type="hidden" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][actions][<?php echo esc_attr( (string) $action_index ); ?>][target_type]" value="<?php echo esc_attr( (string) ( $action['target_type'] ?? 'category' ) ); ?>">
								<label class="wcs-rule-field">
									<span><?php esc_html_e( 'Field', 'woo-spiegelloft-configurator' ); ?></span>
									<select class="wcs-rule-target-group">
										<option value=""><?php esc_html_e( 'Choose field', 'woo-spiegelloft-configurator' ); ?></option>
										<optgroup label="<?php esc_attr_e( 'Measurements', 'woo-spiegelloft-configurator' ); ?>">
											<?php foreach ( $measurement_field_choices as $field_key => $field_label ) : ?>
												<option value="<?php echo esc_attr( $field_key ); ?>" data-target-type="dimension" <?php selected( (string) ( $action['target'] ?? '' ), $field_key ); ?>>
													<?php echo esc_html( $field_label ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
										<?php if ( ! empty( $template_field_choices ) ) : ?>
											<optgroup label="<?php esc_attr_e( 'Choices', 'woo-spiegelloft-configurator' ); ?>">
												<?php foreach ( $template_field_choices as $field_key => $field_label ) : ?>
													<option value="<?php echo esc_attr( $field_key ); ?>" data-target-type="category" <?php selected( (string) ( $action['target'] ?? '' ), $field_key ); ?>>
														<?php echo esc_html( $field_label ); ?>
													</option>
												<?php endforeach; ?>
											</optgroup>
										<?php endif; ?>
									</select>
									<input type="hidden" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][actions][<?php echo esc_attr( (string) $action_index ); ?>][target]" value="<?php echo esc_attr( (string) ( $action['target'] ?? '' ) ); ?>">
								</label>
								<label class="wcs-rule-field wcs-rule-target-value-field">
									<span><?php esc_html_e( 'Option value/path', 'woo-spiegelloft-configurator' ); ?></span>
									<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][actions][<?php echo esc_attr( (string) $action_index ); ?>][target_value]" value="<?php echo esc_attr( (string) ( $action['target_value'] ?? '' ) ); ?>" placeholder="option-value or group.field">
								</label>
								<label class="wcs-rule-field wcs-rule-action-value">
									<span><?php esc_html_e( 'Value/formula', 'woo-spiegelloft-configurator' ); ?></span>
									<input type="text" name="wcs_validation_rules[<?php echo esc_attr( (string) $index ); ?>][actions][<?php echo esc_attr( (string) $action_index ); ?>][value]" value="<?php echo esc_attr( (string) ( $action['value'] ?? $action['max'] ?? '' ) ); ?>" placeholder="{width} - 100">
								</label>
								<div class="wcs-rule-row-actions">
									<button type="button" class="button wcs-add-action"><?php esc_html_e( 'Add', 'woo-spiegelloft-configurator' ); ?></button>
									<button type="button" class="button wcs-remove-action"><?php esc_html_e( 'Remove', 'woo-spiegelloft-configurator' ); ?></button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wcs-rule-section">
					<div class="wcs-rule-grid">
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
