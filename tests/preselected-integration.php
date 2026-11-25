<?php
/** Disposable WordPress checks for saving, normalization and layout transfer. */
declare(strict_types=1);
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
if ( empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$count = 0;
$check = static function ( bool $valid, string $message ) use ( &$count ): void {
	if ( ! $valid ) { throw new RuntimeException( $message ); }
	++$count;
	echo 'PASS: ' . $message . PHP_EOL;
};
$cache = new WCS_Cache();
$template = new WCS_Template( $cache );
$catalog = new WCS_Extras_Catalog( $cache );
$admin = ( new ReflectionClass( WCS_Choice_Meta::class ) )->newInstanceWithoutConstructor();
$sanitize = new ReflectionMethod( $admin, 'sanitize_customer_fields' );
$raw = array( array( 'label' => 'Position', 'key' => 'position', 'type' => 'dropdown', 'options' => array(
	array( 'label' => 'Links', 'value' => 'links', 'preselected' => true, 'nested_enabled' => true, 'customer_fields' => array(
		array( 'label' => 'Unteroption', 'key' => 'nested', 'type' => 'dropdown', 'options' => array(
			array( 'label' => 'Oben', 'value' => 'oben', 'preselected' => true ), array( 'label' => 'Unten', 'value' => 'unten' ),
		) ),
	) ),
	array( 'label' => 'Rechts', 'value' => 'rechts', 'preselected' => true ),
) ) );
$fields = $sanitize->invoke( $admin, $raw );
$check( $fields[0]['options'][0]['preselected'] && ! $fields[0]['options'][1]['preselected'], 'Server accepts only one default in each dropdown' );
$check( $fields[0]['options'][0]['customer_fields'][0]['options'][0]['preselected'], 'Nested default remains independent' );
$raw[0]['options'][0]['preselected'] = false;
$raw[0]['options'][1]['preselected'] = false;
$empty = $sanitize->invoke( $admin, $raw );
$check( ! array_filter( array_column( $empty[0]['options'], 'preselected' ) ), 'All defaults can be cleared' );
$normalizer = new ReflectionMethod( WCS_Config_Builder::class, 'normalize_customer_field' );
$normalized = $normalizer->invoke( wcs_get_config_builder(), $raw[0] );
$check( ! $normalized['options'][0]['preselected'] && ! $normalized['options'][1]['preselected'], 'Unselected defaults survive storefront normalization' );
$normalized = $normalizer->invoke( wcs_get_config_builder(), $fields[0] );
$check( $normalized['options'][0]['preselected'] && $normalized['options'][0]['customer_fields'][0]['options'][0]['preselected'], 'Storefront payload retains defaults at each depth' );
$option_id = 0;
$layout_id = 0;
$imported_id = 0;
$imported_options = array();
try {
	$option_id = $catalog->save_option( array( 'title' => 'WCS Default Test ' . wp_generate_uuid4(), 'slug' => 'default-test', 'meta' => array( '_wcs_option_data' => array( 'name' => 'Position', 'value' => 'position', 'customer_fields' => $fields ) ) ) );
	$layout_id = wp_insert_post( array( 'post_type' => 'wcs_template', 'post_status' => 'publish', 'post_title' => 'WCS Default Test' ) );
	$template->save_template_data( $layout_id, array( 'slug' => 'default-test', 'enabled_groups' => array( 'bluetooth' ), 'extra_option_map' => array( 'bluetooth' => array( $option_id ) ), 'choice_order' => array( $option_id ) ) );
	$fresh = get_post_meta( $option_id, '_wcs_option_data', true );
	$check( $fresh['customer_fields'][0]['options'][0]['preselected'], 'Fresh database read retains the selected default' );
	// Reproduce a catalogue snapshot populated before customer-field metadata changed.
	wp_set_object_terms( $option_id, 'bluetooth', 'wcs_extra_group' );
	$old = $fresh;
	$old['customer_fields'][0]['options'][0]['preselected'] = false;
	update_post_meta( $option_id, '_wcs_option_data', $old );
	$cache->bump_version();
	$cached_options = $catalog->get_options_by_group( 'bluetooth' );
	$cached_option = array_values( array_filter( $cached_options, static function ( $option ) use ( $option_id ) { return $option['id'] === $option_id; } ) )[0];
	update_post_meta( $option_id, '_wcs_option_data', $fresh );
	$format = new ReflectionMethod( WCS_Config_Builder::class, 'format_shopify_option' );
	$payload = $format->invoke( wcs_get_config_builder(), $cached_option, array() );
	$check( $payload['customer_fields'][0]['options'][0]['preselected'], 'Saved default takes precedence over an older cached catalogue snapshot' );
	$package = new WCS_Layout_Package();
	$export = ( new WCS_Layout_Exporter( $template, new WCS_Extras_Registry(), $catalog, $package ) )->export( $layout_id );
	$check( ! is_wp_error( $export ), 'Layout exports with preselected options' );
	$document = json_decode( $export, true );
	$document['template']['title'] .= ' imported ' . wp_generate_uuid4();
	$export = wp_json_encode( $document );
	$import = ( new WCS_Layout_Importer( $package, $cache ) )->import( $export );
	$check( ! is_wp_error( $import ), 'Layout imports with preselected options' );
	$imported_id = $import['template_id'];
	$data = $template->get_template_data( $imported_id );
	$imported_options = $data['extra_option_map']['bluetooth'];
	$imported = get_post_meta( $imported_options[0], '_wcs_option_data', true );
	$check( $imported['customer_fields'][0]['options'][0]['preselected'] && ! $imported['customer_fields'][0]['options'][1]['preselected'], 'Import/export preserves the exclusive default' );
	echo "Completed $count preselection integration checks.\n";
} finally {
	foreach ( array_merge( array( $option_id, $layout_id, $imported_id ), $imported_options ) as $id ) { if ( $id ) { wp_delete_post( $id, true ); } }
	$cache->bump_version();
}
