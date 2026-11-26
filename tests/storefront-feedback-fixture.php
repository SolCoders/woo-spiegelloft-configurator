<?php
/** Render the real shortcode for checking feedback independently of theme templates. */
declare(strict_types=1);
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
ob_start();
require __DIR__ . '/pricing-browser-fixture.php';
$fixture = json_decode( ob_get_clean(), true );
if ( 'setup' === ( $argv[2] ?? '' ) ) {
	$option = get_post_meta( $fixture['option_id'], '_wcs_option_data', true );
	$option['name'] = 'Zusatzoption';
	$option['price'] = 0;
	$option['customer_fields'][0]['price_enabled'] = true;
	$option['customer_fields'][0]['options'][0]['price'] = 50;
	$option['customer_fields'][0]['options'][0]['customer_fields'] = array( array( 'key' => 'sensor-position', 'label' => 'Position des Schalters/Sensors', 'type' => 'dropdown', 'options' => array( array( 'label' => 'Unten Links', 'value' => 'bottom-left' ) ) ) );
	$option['customer_fields'][0]['options'][0]['customer_fields'][0]['options'][0]['customer_fields'] = array( array( 'key' => 'distance-to-the-side-mm', 'label' => 'Abstand zur Seitenkante des Spiegels (mm)', 'type' => 'number', 'min' => 150 ) );
	$option['customer_fields'][0]['label'] = 'Oberfläche';
	$option['customer_fields'][0]['options'][0]['label'] = 'Klemmprofil + Stabilisationsstange 900 mm, kürzbar, hell verchromt';
	$option['customer_fields'][] = array( 'key' => 'empty_note', 'label' => 'Zusätzlicher Hinweis', 'type' => 'text', 'required' => false );
	$option['customer_fields'][] = array( 'key' => 'empty_distance', 'label' => 'Zusätzlicher Abstand', 'type' => 'number', 'required' => false );
	$option['customer_fields'][] = array( 'key' => 'minimum_distance', 'label' => 'Seitenabstand', 'type' => 'number', 'min' => 150, 'required' => true );
	$option['customer_fields'][] = array( 'key' => 'zero_minimum', 'label' => 'Nullwert', 'type' => 'number', 'min' => 0, 'required' => false );
	$option['customer_fields'][] = array( 'key' => 'make-up-mirror', 'label' => 'Schminkspiegel', 'type' => 'dropdown', 'options' => array( array( 'label' => 'Unbeleuchtet', 'value' => 'unbeleuchtet' ), array( 'label' => 'Beleuchtet', 'value' => 'beleuchtet', 'price' => 67.99 ) ) );
	update_post_meta( $fixture['option_id'], '_wcs_option_data', $option );
	$duplicate = wp_insert_post( array( 'post_type' => 'wcs_extra_option', 'post_status' => 'publish', 'post_title' => $fixture['marker'] . ' Other template option' ) );
	update_post_meta( $duplicate, '_wcs_option_data', $option );
	$fixture['extra_option_ids'] = array( $duplicate );
	$product = wc_get_product( $fixture['product_id'] );
	$product->set_regular_price( '83' );
	$product->save();
	$settings = get_post_meta( $fixture['layout_id'], '_wcs_template_data', true );
	$path = 'bluetooth__choice_' . $fixture['option_id'] . '.browser-fixture.';
	$rule = array(
		'rule_type' => 'required_field', 'match' => 'all',
		'conditions' => array( array( 'source' => 'nested', 'path' => $path . 'finish', 'field' => 'value', 'type' => 'selection', 'operator' => 'equals', 'value' => 'brass' ) ),
		'actions' => array( array( 'action' => 'validate_range', 'target_type' => 'nested', 'target' => $path . 'minimum_distance', 'value' => '{width} - 100' ) ),
		'message' => 'Glass shelf length must fit inside the mirror width.', 'error_seconds' => 5, 'restore' => true,
		'then' => 'validate_range', 'target_type' => 'nested', 'target' => $path . 'minimum_distance', 'target_value' => '', 'min' => '', 'max' => '{width} - 100',
	);
	$required = array( 'conditions' => array( array( 'source' => 'nested', 'path' => $path . 'finish', 'operator' => 'equals', 'value' => 'brass' ) ), 'actions' => array( array( 'action' => 'require', 'target_type' => 'nested', 'target' => $path . 'finish.brass.sensor-position' ) ), 'message' => 'Switch/sensor position is required' );
	$clear = $required;
	$clear['actions'][0]['action'] = 'clear';
	$settings['validation_rules'] = array( $rule, $required, $clear );
	$settings['behavior_rules'] = array( $rule, $required, $clear );
	$moved_distance = array( 'conditions' => array( array( 'source' => 'nested', 'path' => $path . 'finish.brass.sensor-position', 'operator' => 'equals', 'value' => 'bottom-left' ) ), 'actions' => array( array( 'action' => 'require', 'target_type' => 'nested', 'target' => $path . 'finish.brass.distance-to-the-side-mm' ) ), 'message' => 'Distance to the side is required' );
	$settings['validation_rules'][] = $moved_distance;
	$settings['behavior_rules'][] = $moved_distance;
	foreach ( array( 'side_width', 'side_height' ) as $dimension ) {
		$availability = array( 'match' => 'all', 'conditions' => array(
			array( 'source' => 'dimension', 'path' => $dimension, 'type' => 'number', 'operator' => 'less_than', 'value' => '500' ),
			array( 'source' => 'nested', 'path' => $path . 'make-up-mirror', 'operator' => 'equals', 'value' => 'beleuchtet' ),
		), 'actions' => array( array( 'action' => 'disable', 'target_type' => 'nested', 'target' => $path . 'make-up-mirror', 'target_value' => 'beleuchtet' ) ) );
		$settings['validation_rules'][] = $availability;
		$settings['behavior_rules'][] = $availability;
	}
	update_post_meta( $fixture['layout_id'], '_wcs_template_data', $settings );
	( new WCS_Cache() )->bump_version();
	$config = wcs_get_config_builder()->build_for_product( $fixture['product_id'] );
	$fixture['config_error'] = is_wp_error( $config ) ? $config->get_error_message() : '';
	wp_set_current_user( 0 );
	$fixture['html'] = do_shortcode( '[wcs_configurator product_id="' . $fixture['product_id'] . '"]' );
}
echo wp_json_encode( $fixture );
