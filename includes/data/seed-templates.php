<?php
/**
 * Seed data for default mirror templates and their editable restrictions.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$groups = array(
	'suspension_plates',
	'clock',
	'bluetooth',
	'glass_shelf',
	'led_strip',
	'light_color',
	'makeup_mirror',
	'material',
	'mirror_heating',
	'plug_socket',
	'sealing',
	'side_panels',
	'sockets',
	'switch_sensor',
);

$step_map = array(
	'suspension_plates' => 1,
	'clock'             => 2,
	'bluetooth'         => 2,
	'glass_shelf'       => 2,
	'led_strip'         => 2,
	'light_color'       => 3,
	'switch_sensor'     => 3,
	'mirror_heating'    => 3,
	'makeup_mirror'     => 4,
	'side_panels'       => 5,
	'material'          => 5,
	'sockets'           => 5,
	'plug_socket'       => 5,
	'sealing'           => 5,
);

$rule = static function (
	string $rule_type,
	string $when_source,
	string $when_path,
	string $operator,
	string $when_value,
	string $then,
	string $target_type,
	string $target,
	string $target_value = '',
	string $min = '',
	string $max = '',
	string $message = '',
	int $seconds = 4,
	bool $restore = false,
	string $field = 'value'
): array {
	return array(
		'rule_type'     => $rule_type,
		'when_source'   => $when_source,
		'when_path'     => $when_path,
		'when_group'    => 'category' === $when_source ? $when_path : '',
		'when_field'    => $field,
		'when_operator' => $operator,
		'when_value'    => $when_value,
		'when'          => array( $when_path => $when_value ),
		'then'          => $then,
		'target_type'   => $target_type,
		'target'        => $target,
		'target_value'  => $target_value,
		'min'           => $min,
		'max'           => $max,
		'message'       => $message,
		'error_seconds' => $seconds,
		'restore'       => $restore,
	);
};

$range = static function ( string $path, string $min, string $max, string $message, int $seconds = 4 ) use ( $rule ): array {
	return $rule( 'numeric_range', 'nested', $path, 'selected', '', 'validate_range', 'nested', $path, $path, $min, $max, $message, $seconds, true );
};

$require = static function ( string $when_path, string $target_path, string $message ) use ( $rule ): array {
	return $rule( 'required_field', 'category', $when_path, 'selected', '', 'require', 'nested', $target_path, $target_path, '', '', $message );
};

$clear = static function ( string $when_path, string $target_path, string $message ) use ( $rule ): array {
	return $rule( 'clear_dependent', 'category', $when_path, 'empty', '', 'clear', 'nested', $target_path, $target_path, '', '', $message );
};

$disable_value = static function ( string $when_source, string $when_path, string $operator, string $when_value, string $target, string $target_value, string $message ) use ( $rule ): array {
	return $rule( 'disable_option', $when_source, $when_path, $operator, $when_value, 'disable_option', 'category', $target, $target_value, '', '', $message );
};

$common_rules = static function ( string $size_field = 'width', string $height_field = 'height' ) use ( $rule, $range, $require, $clear, $disable_value ): array {
	return array(
		$disable_value( 'dimension', $size_field, 'less_than', '500', 'makeup_mirror', 'beleuchtet', 'Illuminated makeup mirror requires minimum dimensions of 500 x 500 mm.' ),
		$disable_value( 'dimension', $height_field, 'less_than', '500', 'makeup_mirror', 'beleuchtet', 'Illuminated makeup mirror requires minimum dimensions of 500 x 500 mm.' ),
		$require( 'makeup_mirror', 'makeup_mirror.type', 'Choose the makeup mirror type.' ),
		$require( 'makeup_mirror', 'makeup_mirror.position', 'Choose the makeup mirror position.' ),
		$require( 'makeup_mirror', 'makeup_mirror.side_distance', 'Enter the makeup mirror side distance.' ),
		$require( 'makeup_mirror', 'makeup_mirror.bottom_distance', 'Enter the makeup mirror bottom distance.' ),
		$range( 'makeup_mirror.side_distance', '150', 'floor(' . $size_field . ' / 2)', 'Makeup mirror side distance is outside the allowed range.' ),
		$range( 'makeup_mirror.bottom_distance', '150', $height_field . ' - 150', 'Makeup mirror bottom distance is outside the allowed range.' ),
		$rule( 'required_field', 'category', 'makeup_mirror', 'equals', 'beleuchtet', 'require', 'nested', 'makeup_mirror.light_color', 'makeup_mirror.light_color', '', '', 'Choose a light color for the illuminated makeup mirror.' ),
		$rule( 'required_field', 'nested', 'makeup_mirror.switch', 'equals', 'toggle-switch', 'require', 'nested', 'makeup_mirror.switch_position', 'makeup_mirror.switch_position', '', '', 'Choose the switch position.' ),
		$rule( 'required_field', 'nested', 'makeup_mirror.switch', 'equals', 'touch-sensor', 'require', 'nested', 'makeup_mirror.switch_position', 'makeup_mirror.switch_position', '', '', 'Choose the touch sensor position.' ),

		$require( 'clock', 'clock.position', 'Choose the digital clock position.' ),
		$clear( 'clock', 'clock.position', 'Clock position is cleared when no clock is selected.' ),

		$rule( 'show_hide', 'category', 'light_color', 'equals', 'neutral', 'show', 'category', 'led_strip', 'led_strip', '', '', 'Show LED strip for fixed LED colors.' ),
		$rule( 'show_hide', 'category', 'light_color', 'equals', 'warm', 'show', 'category', 'led_strip', 'led_strip', '', '', 'Show LED strip for fixed LED colors.' ),
		$rule( 'show_hide', 'category', 'light_color', 'equals', 'cold', 'show', 'category', 'led_strip', 'led_strip', '', '', 'Show LED strip for fixed LED colors.' ),
		$clear( 'light_color', 'switch_sensor.position', 'Sensor position is cleared when the light color changes.' ),
		$rule( 'required_field', 'category', 'switch_sensor', 'equals', 'toggle-switch', 'require', 'nested', 'switch_sensor.position', 'switch_sensor.position', '', '', 'Choose the switch position.' ),
		$rule( 'required_field', 'category', 'switch_sensor', 'equals', 'touch-sensor', 'require', 'nested', 'switch_sensor.position', 'switch_sensor.position', '', '', 'Choose the sensor position.' ),

		$rule( 'required_field', 'category', 'mirror_heating', 'equals', 'with-toggle-switch', 'require', 'nested', 'mirror_heating.position', 'mirror_heating.position', '', '', 'Choose the heating switch position.' ),
		$rule( 'required_field', 'category', 'mirror_heating', 'equals', 'with-touch-sensor', 'require', 'nested', 'mirror_heating.position', 'mirror_heating.position', '', '', 'Choose the heating sensor position.' ),
		$clear( 'mirror_heating', 'mirror_heating.position', 'Heating control position is cleared when no controlled heating option is selected.' ),

		$require( 'side_panels', 'side_panels.material', 'Choose the aperture material.' ),
		$clear( 'side_panels', 'side_panels.material', 'Aperture material is cleared when side panels are not selected.' ),

		$rule( 'required_field', 'category', 'sockets', 'equals', '1x-socket', 'require', 'nested', 'sockets.color', 'sockets.color', '', '', 'Choose the socket color.' ),
		$rule( 'required_field', 'category', 'sockets', 'equals', '1x-socket', 'require', 'nested', 'sockets.position', 'sockets.position', '', '', 'Choose the socket position.' ),
		$rule( 'required_field', 'category', 'sockets', 'equals', '2x-socket', 'require', 'nested', 'sockets.color', 'sockets.color', '', '', 'Choose the socket color.' ),
		$rule( 'required_field', 'category', 'sockets', 'equals', '2x-socket', 'require', 'nested', 'sockets.position', 'sockets.position', '', '', 'Choose the socket position.' ),
		$clear( 'sockets', 'sockets.position', 'Socket position is cleared when sockets are not selected.' ),
		$clear( 'sockets', 'sockets.color', 'Socket color is cleared when sockets are not selected.' ),
	);
};

$rectangle_rules = array_merge(
	array(
		$range( 'width', 'min_width', 'max_width', 'Width is outside this product range.', 5 ),
		$range( 'height', 'min_height', 'max_height', 'Height is outside this product range.', 5 ),
	),
	$common_rules()
);

$round_rules = array_merge(
	array(
		$range( 'diameter', 'min_width', 'max_width', 'Diameter is outside this product range.', 5 ),
	),
	$common_rules( 'diameter', 'diameter' )
);

$sloping_rules = array_merge(
	array(
		$range( 'top_width', 'top_min_width', 'top_max_width', 'Top width is outside this product range.', 5 ),
		$range( 'bottom_width', 'bottom_min_width', 'bottom_max_width', 'Bottom width is outside this product range.', 5 ),
		$range( 'left_height', 'left_min_height', 'left_max_height', 'Left height is outside this product range.', 5 ),
		$range( 'right_height', 'right_min_height', 'right_max_height', 'Right height is outside this product range.', 5 ),
		$disable_value( 'dimension', 'count_gte(top_width,bottom_width,left_height,right_height,400)', 'less_than', '3', 'makeup_mirror', 'unbeleuchtet', 'Makeup mirror requires at least three sides of 400 mm or more.' ),
		$disable_value( 'dimension', 'count_gte(top_width,bottom_width,left_height,right_height,400)', 'less_than', '3', 'makeup_mirror', 'beleuchtet', 'Makeup mirror requires at least three sides of 400 mm or more.' ),
		$disable_value( 'dimension', 'max(top_width,bottom_width)', 'less_than', '101', 'glass_shelf', 'clear-glass', 'Glass shelf requires a usable top or bottom width.' ),
		$disable_value( 'dimension', 'max(top_width,bottom_width)', 'less_than', '101', 'glass_shelf', 'satin-glass', 'Glass shelf requires a usable top or bottom width.' ),
		$range( 'makeup_mirror.side_distance', '150', 'floor(top_width / 2)', 'Makeup mirror side distance is outside the allowed range.' ),
		$range( 'makeup_mirror.bottom_distance', '150', 'max(left_height,right_height) - 150', 'Makeup mirror bottom distance is outside the allowed range.' ),
	),
	$common_rules( 'top_width', 'right_height' )
);

$template = static function ( string $title, string $slug, string $panel_template, string $type, array $dimensions, array $rules ) use ( $groups, $step_map ): array {
	return array(
		'title'             => $title,
		'panel_template'    => $panel_template,
		'slug'              => $slug,
		'type'              => $type,
		'dimensions'        => $dimensions,
		'enabled_groups'    => $groups,
		'group_order'       => $groups,
		'step_map'          => $step_map,
		'extra_option_map'  => array(),
		'validation_rules'  => $rules,
		'behavior_rules'    => $rules,
		'edge_override'     => array(
			'name'        => 'Kanten',
			'description' => 'geschliffen & poliert',
		),
	);
};

return array(
	$template(
		'Bathroom Mirror',
		'bathroom-mirror',
		'bathroomMirror',
		'bathroom',
		array(
			'min_width'  => 400,
			'max_width'  => 2500,
			'min_height' => 400,
			'max_height' => 2500,
		),
		$rectangle_rules
	),
	$template(
		'Oval Mirror',
		'oval-mirror',
		'ovalMirror',
		'oval',
		array(
			'min_width'  => 400,
			'max_width'  => 2500,
			'min_height' => 400,
			'max_height' => 2500,
		),
		$rectangle_rules
	),
	$template(
		'Round Mirror',
		'round-mirror',
		'roundMirror',
		'round',
		array(
			'min_width'  => 400,
			'max_width'  => 2000,
			'min_height' => 400,
			'max_height' => 2000,
		),
		$round_rules
	),
	$template(
		'Mirror Sloping Roof',
		'mirror-sloping-roof',
		'slopingRoofMirror',
		'sloping_roof',
		array(
			'min_width'        => 0,
			'max_width'        => 2000,
			'min_height'       => 0,
			'max_height'       => 2000,
			'top_min_width'    => 0,
			'top_max_width'    => 2000,
			'bottom_min_width' => 0,
			'bottom_max_width' => 2000,
			'left_min_height'  => 0,
			'left_max_height'  => 2000,
			'right_min_height' => 0,
			'right_max_height' => 2000,
		),
		$sloping_rules
	),
);
