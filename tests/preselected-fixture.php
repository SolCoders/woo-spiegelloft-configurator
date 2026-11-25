<?php
/** Disposable dropdown-default fixture, sharing the existing fixture cleanup. */
declare(strict_types=1);
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
if ( 'render' === ( $argv[2] ?? '' ) ) {
	require $argv[1];
	$data = json_decode( stream_get_contents( STDIN ), true );
	wp_set_current_user( 0 );
	echo wp_json_encode( array( 'html' => do_shortcode( '[wcs_configurator product_id="' . (int) $data['product_id'] . '"]' ), 'option' => get_post_meta( (int) $data['option_id'], '_wcs_option_data', true ) ) );
	exit;
}
ob_start();
require __DIR__ . '/pricing-browser-fixture.php';
$fixture = json_decode( ob_get_clean(), true );
if ( 'setup' === ( $argv[2] ?? '' ) ) {
	$option = get_post_meta( $fixture['option_id'], '_wcs_option_data', true );
	$option['customer_fields'] = array(
		array( 'key' => 'finish', 'label' => 'Position', 'type' => 'dropdown', 'price_enabled' => true, 'options' => array(
			array( 'label' => 'Links', 'value' => 'links', 'price' => 0 ),
			array( 'label' => 'Mitte', 'value' => 'mitte', 'price' => 12, 'nested_enabled' => true, 'customer_fields' => array(
				array( 'key' => 'height', 'label' => 'Einbauposition', 'type' => 'dropdown', 'options' => array(
					array( 'label' => 'Oben', 'value' => 'oben', 'preselected' => true ), array( 'label' => 'Unten', 'value' => 'unten' ),
				) ),
			) ),
			array( 'label' => 'Rechts', 'value' => 'rechts', 'price' => 0 ),
		) ),
		array( 'key' => 'optional', 'label' => 'Weitere Auswahl', 'type' => 'dropdown', 'options' => array(
			array( 'label' => 'Ja', 'value' => 'yes' ), array( 'label' => 'Nein', 'value' => 'no' ),
		) ),
	);
	update_post_meta( $fixture['option_id'], '_wcs_option_data', $option );
	update_post_meta( $fixture['option_id'], '_wcs_import_group', 'bluetooth' );
	( new WCS_Cache() )->bump_version();
}
echo wp_json_encode( $fixture );
