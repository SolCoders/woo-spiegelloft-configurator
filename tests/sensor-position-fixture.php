<?php
/** Replay exported light/switch rules using disposable posts only. */
declare(strict_types=1);
if ( 'setup' === ( $argv[2] ?? '' ) && ( empty( $argv[3] ) || ! is_file( $argv[3] ) ) ) { fwrite( STDERR, "Supply an exported mirror layout JSON path.\n" ); exit( 1 ); }
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
ob_start();
require __DIR__ . '/pricing-browser-fixture.php';
$fixture = json_decode( ob_get_clean(), true );
if ( 'setup' === ( $argv[2] ?? '' ) ) {
	$document = json_decode( file_get_contents( $argv[3] ), true );
	foreach ( $document['options'] as $option ) {
		if ( 'light-color' !== ( $option['meta']['_wcs_option_data']['value'] ?? '' ) ) { continue; }
		update_post_meta( $fixture['option_id'], '_wcs_option_data', $option['meta']['_wcs_option_data'] );
		$old = 'bluetooth__choice_' . $option['id'];
		$new = 'bluetooth__choice_' . $fixture['option_id'];
		$rules = array_values( array_filter( $document['template']['data']['behavior_rules'], static function ( $rule ) use ( $old ) { return false !== strpos( wp_json_encode( $rule ), $old . '.light-color' ); } ) );
		$rules = json_decode( str_replace( $old, $new, wp_json_encode( $rules ) ), true );
		$settings = get_post_meta( $fixture['layout_id'], '_wcs_template_data', true );
		$settings['validation_rules'] = $rules; $settings['behavior_rules'] = $rules;
		update_post_meta( $fixture['layout_id'], '_wcs_template_data', $settings );
	}
	$product = wc_get_product( $fixture['product_id'] ); $product->set_regular_price( '83' ); $product->save();
	( new WCS_Cache() )->bump_version();
	wp_set_current_user( 0 );
	$fixture['html'] = do_shortcode( '[wcs_configurator product_id="' . $fixture['product_id'] . '"]' );
}
echo wp_json_encode( $fixture );
