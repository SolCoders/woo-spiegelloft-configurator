<?php
/** Disposable integration tests. Usage: php tests/pricing-integration.php /path/to/wp-load.php */
declare(strict_types=1);
if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$checks = 0;
$check = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$configured_decimals = 0;
$decimal_setting = static function () use ( &$configured_decimals ) { return $configured_decimals; };
add_filter( 'pre_option_woocommerce_price_num_decimals', $decimal_setting );
try {
	foreach ( array( 0 => 2, 1 => 2, 2 => 2, 3 => 3 ) as $configured_decimals => $expected_decimals ) {
		$check( wc_get_price_decimals() === $expected_decimals, 'Currency precision keeps at least two decimal places' );
		$check( 122.99 === wcs_get_config_builder()->calculate_price( 122.99, array() ), 'Configured price preserves cents with a whole-number store setting' );
	}
} finally {
	remove_filter( 'pre_option_woocommerce_price_num_decimals', $decimal_setting );
}
$fields = array( array( 'key' => 'width', 'label' => 'Width', 'min' => 400, 'max' => 2600 ), array( 'key' => 'height', 'label' => 'Height', 'min' => 400, 'max' => 2600 ) );
$pricing = WCS_Pricing::defaults( $fields );
$rules = WCS_Pricing::default_rules();
$sum = 0;
foreach ( $rules as $rule ) {
	$check( abs( WCS_Pricing::dimension( $rule['from'] - 1, $rules ) - $sum ) < .00001, 'Below threshold ' . $rule['from'] );
	$sum += $rule['amount'];
	$check( abs( WCS_Pricing::dimension( $rule['from'], $rules ) - $sum ) < .00001, 'At threshold ' . $rule['from'] );
}
$check( WCS_Pricing::dimension( 525, $rules ) === 32.5, '525 cumulative surcharge' );
foreach ( array( array( 400, 400, 0 ), array( 401, 400, 69 ), array( 1200, 700, 69 ), array( 1201, 701, 103 ), array( 701, 1201, 103 ), array( 1300, 1400, 103 ), array( 1200, 701, 69 ) ) as $case ) {
	$check( WCS_Pricing::shipping( array( $case[0], $case[1] ), 100, $pricing['shipping'] )['total'] == $case[2], 'Shipping boundary ' . json_encode( $case ) );
}
$check( WCS_Pricing::shipping( array( 1300 ), 100, $pricing['shipping'] )['total'] === 103.0, 'Diameter shipping' );
$check( WCS_Pricing::shipping( array( 1300, 1400, 1500 ), 100, $pricing['shipping'] )['total'] === 0.0, 'Three fields disable shipping' );
$off = $pricing['shipping']; $off['enabled'] = false;
$check( WCS_Pricing::shipping( array( 1300, 1400 ), 100, $off )['total'] === 0.0, 'Disabled shipping removes markup' );
$custom = $pricing; $custom['dimensions']['side_width'] = array();
$check( WCS_Pricing::normalize( $custom, $fields )['dimensions']['side_width'] === array(), 'Explicit empty rules persist' );
$custom['dimensions']['side_deleted'] = $rules;
$check( ! isset( WCS_Pricing::normalize( $custom, $fields )['dimensions']['side_deleted'] ), 'Unknown field rules removed' );
$custom['shipping']['groups'][] = $custom['shipping']['groups'][0];
$check( count( WCS_Pricing::normalize( $custom, $fields )['shipping']['groups'] ) === 2, 'Markup group cannot duplicate' );
$template = new WCS_Template( new WCS_Cache() );
$id = wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => 'Pricing integration fixture', 'post_status' => 'draft' ) );
$product = new WC_Product_Simple(); $product->set_name( 'Pricing integration fixture' ); $product->set_regular_price( '100' ); $product->set_status( 'publish' ); $product_id = $product->save();
$imported_id = 0;
try {
	$template->save_template_data( $id, array( 'dimensions' => array( 'side_measurements' => $fields ), 'pricing' => $pricing ) );
	$template->set_product_template_id( $product_id, $id );
	$data = $template->get_template_data( $id );
	$check( $data['pricing'] == $pricing, 'Save/reload pricing' );
	$builder = wcs_get_config_builder();
	$selections = array( 'side_width' => 525, 'side_height' => 400 );
	$expected = round( 100 + 32.5 + 36 + 100 * .33, wc_get_price_decimals() );
	$check( abs( $builder->calculate_price( 100, $selections, $data ) - $expected ) < .001, 'Backend markup uses product price, not dimension subtotal' );
	$check( WCS_Pricing::calculate( $data, $selections, 100 )['shipping'] === 69.0, 'Product price supplies the shipping markup' );
	$check( WCS_Pricing::calculate( $data, array( 'side_width' => 575, 'side_height' => 400 ), 100 )['shipping'] === 69.0, 'Dimension subtotal changes do not change the markup' );
	$check( WCS_Pricing::calculate( $data, $selections, 200 )['shipping'] === 102.0, 'Product price changes do change the markup' );
	$check( $builder->calculate_price( 100, $selections ) === 100.0, 'Legacy caller without context remains compatible' );
	$cart = $builder->build_cart_configuration( $product_id, $selections );
	$check( ! is_wp_error( $cart ) && abs( $cart['price'] - $expected ) < .001, 'Fresh cart configuration uses trusted pricing' );
	$request = new WP_REST_Request( 'POST', '/wcs/v1/price' ); $request->set_param( 'product_id', $product_id ); $request->set_param( 'selections', $selections );
	$controller = new WCS_REST_Controller( $builder, new WCS_Validation_Engine() );
	$response = $controller->calculate_price( $request );
	$check( abs( $response->get_data()['price'] - $expected ) < .001, 'REST price equals cart price' );
	$one = array( 'dimensions' => array( 'side_measurements' => array( $fields[0] ) ) );
	$check( WCS_Pricing::calculate( $one, array( 'side_width' => 525 ) )['dimensions'] === 32.5, 'One field charged once' );
	$duplicate = $fields; $duplicate[] = $fields[0];
	$check( ! $template->save_template_data( $id, array( 'dimensions' => array( 'side_measurements' => $duplicate ) ) ), 'Duplicate field keys rejected' );
	$normalized = WCS_Pricing::normalize( array(), $fields );
	$check( $normalized == $pricing, 'Legacy templates receive defaults' );
	$check( $data['dimensions']['side_measurements'][0]['step'] == 1, 'Legacy size fields default to a step of one' );
	$check( WCS_Template::measurement_step( 0 ) === 1.0 && WCS_Template::measurement_step( -5 ) === 1.0, 'Invalid steps fall back to one' );
	$check( is_wp_error( $builder->build_cart_configuration( $product_id, array( 'side_width' => 525 ) ) ), 'Missing size cannot bypass dimension and shipping charges' );
	$check( is_wp_error( $builder->build_cart_configuration( $product_id, array( 'side_width' => -5, 'side_height' => 400 ) ) ), 'Invalid size rejected at add-to-cart' );
	$custom = $pricing; $custom['dimensions']['side_width'][0]['amount'] = 31.25; $custom['dimensions']['side_height'] = array(); $custom['shipping']['enabled'] = false;
	$fields[0]['step'] = 10;
	$fields[1]['step'] = 0.5;
	$template->save_template_data( $id, array( 'slug' => 'pricing-fixture', 'dimensions' => array( 'side_measurements' => $fields ), 'pricing' => $custom ) );
	$check( $template->get_template_data( $id )['dimensions']['side_measurements'][0]['step'] === 10.0, 'Custom measurement step persists' );
	$cache = new WCS_Cache(); $package = new WCS_Layout_Package();
	$exporter = new WCS_Layout_Exporter( $template, new WCS_Extras_Registry(), new WCS_Extras_Catalog( $cache ), $package );
	$json = $exporter->export( $id );
	$check( ! is_wp_error( $json ), 'Custom pricing exports' );
	$document = json_decode( $json, true );
	$document['template']['title'] .= ' imported ' . wp_generate_uuid4();
	$json = wp_json_encode( $document );
	$importer = new WCS_Layout_Importer( $package, $cache ); $import = $importer->import( $json );
	$check( ! is_wp_error( $import ), 'Custom pricing imports' );
	$imported_id = $import['template_id'];
	$check( $template->get_template_data( $imported_id )['pricing'] == $custom, 'Pricing, empty rules and shipping switch survive transfer' );
	$imported_fields = $template->get_template_data( $imported_id )['dimensions']['side_measurements'];
	$check( $imported_fields[0]['step'] == 10 && $imported_fields[1]['step'] == 0.5, 'Whole and decimal steps survive transfer' );
	echo 'PASS: ' . $checks . " pricing integration checks.\n";
} finally {
	if ( $imported_id ) { wp_delete_post( $imported_id, true ); }
	wp_delete_post( $product_id, true ); wp_delete_post( $id, true );
}
