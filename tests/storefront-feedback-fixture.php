<?php
/** Render the real shortcode for checking feedback independently of theme templates. */
declare(strict_types=1);
ob_start();
require __DIR__ . '/pricing-browser-fixture.php';
$fixture = json_decode( ob_get_clean(), true );
if ( 'setup' === ( $argv[2] ?? '' ) ) {
	$option = get_post_meta( $fixture['option_id'], '_wcs_option_data', true );
	$option['name'] = 'Zusatzoption';
	$option['customer_fields'][0]['label'] = 'Oberfläche';
	$option['customer_fields'][0]['options'][0]['label'] = 'Messing poliert';
	$option['customer_fields'][] = array( 'key' => 'empty_note', 'label' => 'Zusätzlicher Hinweis', 'type' => 'text', 'required' => false );
	$option['customer_fields'][] = array( 'key' => 'empty_distance', 'label' => 'Zusätzlicher Abstand', 'type' => 'number', 'min' => 0, 'required' => false );
	update_post_meta( $fixture['option_id'], '_wcs_option_data', $option );
	( new WCS_Cache() )->bump_version();
	$config = wcs_get_config_builder()->build_for_product( $fixture['product_id'] );
	$fixture['config_error'] = is_wp_error( $config ) ? $config->get_error_message() : '';
	wp_set_current_user( 0 );
	$fixture['html'] = do_shortcode( '[wcs_configurator product_id="' . $fixture['product_id'] . '"]' );
}
echo wp_json_encode( $fixture );
