<?php
/** Disposable regression for identical option values across mirror templates. */
declare(strict_types=1);
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
require $argv[1];
$ids = array();
$checks = 0;
$check = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
try {
	$cache = new WCS_Cache();
	$template = new WCS_Template( $cache );
	$marker = 'Option price regression ' . wp_generate_uuid4();
	$value = 'price-regression-switch';
	for ( $i = 0; $i < 20; ++$i ) {
		$id = wp_insert_post( array( 'post_type' => 'wcs_extra_option', 'post_title' => $marker . ' ' . $i, 'post_status' => 'publish' ) );
		$ids[] = $id;
		update_post_meta( $id, '_wcs_option_data', array( 'name' => 'Schalter/Sensor', 'value' => $value, 'price' => 0, 'customer_fields' => array(
			array( 'key' => 'switch', 'label' => 'Schalter/Sensor', 'type' => 'dropdown', 'price_enabled' => true, 'options' => array(
				array( 'label' => 'WiFi Alexa', 'value' => 'wifi', 'price' => 50 ),
				array( 'label' => 'Standard', 'value' => 'standard', 'price' => 0 ),
			) ),
		) ) );
	}
	$option_id = $ids[0];
	$layout_id = wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => $marker, 'post_status' => 'publish' ) );
	$ids[] = $layout_id;
	$template->save_template_data( $layout_id, array( 'enabled_groups' => array( 'bluetooth' ), 'extra_option_map' => array( 'bluetooth' => array( $option_id ) ), 'choice_step_map' => array( $option_id => 1 ) ) );
	$product = new WC_Product_Simple();
	$product->set_name( $marker ); $product->set_regular_price( '83' ); $product->set_status( 'publish' );
	$product_id = $product->save(); $ids[] = $product_id;
	$template->set_product_template_id( $product_id, $layout_id );
	$cache->bump_version();
	$data = $template->get_template_data( $layout_id );
	$group = 'bluetooth__choice_' . $option_id;
	$selections = array( 'side_width' => 400, 'side_height' => 400, $group => $value, $group . '.' . $value . '.switch' => 'wifi' );
	$builder = wcs_get_config_builder();
	$price = $builder->calculate_price( 83, $selections, $data );
	$check( 133.0 === $price, '83 + 50 must equal 133 despite 20 identical catalogue option values; got ' . $price );
	$config = $builder->build_cart_configuration( $product_id, $selections );
	$check( ! is_wp_error( $config ) && 133.0 === $config['price'], 'Fresh cart configuration charges one selected option' );
	$selections[ $group . '.' . $value . '.switch' ] = 'standard';
	$check( 83.0 === $builder->calculate_price( 83, $selections, $data ), 'Free option preserves base price' );
	unset( $selections[ $group . '.' . $value . '.switch' ] );
	$check( 83.0 === $builder->calculate_price( 83, $selections, $data ), 'Unfilled dropdown adds no price' );
	$own = get_post_meta( $option_id, '_wcs_option_data', true );
	$own['price'] = 10;
	$own['customer_fields'][0]['options'][0]['price'] = 25;
	update_post_meta( $option_id, '_wcs_option_data', $own );
	$selections[ $group . '.' . $value . '.switch' ] = 'wifi';
	$check( 118.0 === $builder->calculate_price( 83, $selections, $data ), 'Current template root and dropdown prices override unrelated cached copies' );
	$other_group = 'bluetooth__choice_' . $ids[1];
	$selections[ $other_group ] = $value;
	$selections[ $other_group . '.' . $value . '.switch' ] = 'wifi';
	$check( 118.0 === $builder->calculate_price( 83, $selections, $data ), 'Options outside the template cannot add charges' );
	unset( $selections[ $other_group ], $selections[ $other_group . '.' . $value . '.switch' ] );
	$grouped = $data;
	$grouped['choice_step_map'] = array();
	$grouped_selections = array( 'side_width' => 400, 'side_height' => 400, 'bluetooth' => $value, 'bluetooth.' . $value . '.switch' => 'wifi' );
	$check( 118.0 === $builder->calculate_price( 83, $grouped_selections, $grouped ), 'Grouped layouts also use only their own option metadata' );
	echo 'PASS: ' . $checks . " option price integration checks.\n";
} finally {
	foreach ( array_reverse( $ids ) as $id ) { wp_delete_post( $id, true ); }
	( new WCS_Cache() )->bump_version();
}
