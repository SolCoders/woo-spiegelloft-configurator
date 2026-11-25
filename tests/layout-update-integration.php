<?php
/** Disposable WordPress checks for name-matched layout updates. */
declare(strict_types=1);
define( 'WP_ADMIN', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
require $argv[1];
$created = array();
$track = static function ( int $id, WP_Post $post, bool $update ) use ( &$created ): void {
	if ( ! $update ) { $created[] = $id; }
};
add_action( 'wp_insert_post', $track, 10, 3 );
$checks = 0;
$check = static function ( bool $valid, string $message ) use ( &$checks ): void {
	if ( ! $valid ) { throw new RuntimeException( $message ); }
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
};
$expect = static function ( $result ) {
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_code() . ': ' . $result->get_error_message() ); }
	return $result;
};
$cache = new WCS_Cache();
$templates = new WCS_Template( $cache );
$catalog = new WCS_Extras_Catalog( $cache );
$registry = new WCS_Extras_Registry();
$package = new WCS_Layout_Package();
$importer = new WCS_Layout_Importer( $package, $cache );
$exporter = new WCS_Layout_Exporter( $templates, $registry, $catalog, $package );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$prefix = 'WCS Update ' . wp_generate_uuid4();
$image = 'https://example.invalid/already-uploaded.jpg';
$http = 0;
$block = static function () use ( &$http ) { ++$http; return new WP_Error( 'no_http', 'No media HTTP expected.' ); };
add_filter( 'pre_http_request', $block );
try {
	$source_option = $expect( $catalog->save_option( array( 'title' => $prefix . ' Option', 'slug' => 'clock', 'meta' => array(
		'_wcs_option_data' => array( 'name' => 'Digitaluhr', 'value' => 'clock', 'price' => 10, 'image' => '', 'customer_fields' => array(
			array( 'key' => 'position', 'label' => 'Position', 'type' => 'dropdown', 'options' => array(
				array( 'value' => 'left', 'label' => 'Links', 'image' => '', 'preselected' => true, 'price' => 2 ),
				array( 'value' => 'right', 'label' => 'Rechts', 'image' => '', 'price' => 3 ),
			) ),
			array( 'key' => 'distance', 'label' => 'Abstand', 'type' => 'number', 'min' => 150, 'max' => 500 ),
		) ), '_wcs_price' => 10, '_wcs_image' => '',
	) ) ) );
	$source = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_status' => 'publish', 'post_title' => $prefix . ' Source' ), true ) );
	$rule = array( 'rule_type' => 'visibility', 'match' => 'all', 'conditions' => array( array( 'source' => 'nested', 'path' => 'bluetooth__choice_' . $source_option . '.position', 'field' => 'value', 'operator' => 'equals', 'value' => 'left', 'type' => 'selection' ) ), 'actions' => array( array( 'action' => 'set_max', 'target_type' => 'nested', 'target' => 'bluetooth__choice_' . $source_option . '.distance', 'value' => '450' ) ) );
	$templates->save_template_data( $source, array( 'slug' => 'update-source', 'enabled_groups' => array( 'bluetooth' ), 'extra_option_map' => array( 'bluetooth' => array( $source_option ) ), 'choice_order' => array( $source_option ), 'choice_step_map' => array( $source_option => 2 ), 'validation_rules' => array( $rule ), 'behavior_rules' => array( $rule ) ) );
	$document = $expect( $package->decode( $expect( $exporter->export( $source ) ) ) );
	$document['template']['title'] = $prefix . ' Target';
	$result = $expect( $importer->import( wp_json_encode( $document ) ) );
	$target = $result['template_id'];
	$target_data = $templates->get_template_data( $target );
	$option = $target_data['choice_order'][0];
	$check( 'created' === $result['action'] && $target !== $source && $option !== $source_option, 'Unmatched name creates an independent template and option' );
	$product = new WC_Product_Simple();
	$product->set_name( $prefix . ' Product' );
	$product->set_regular_price( '100' );
	$product->set_status( 'publish' );
	$product_id = $product->save();
	$templates->set_product_template_id( $product_id, $target );
	update_post_meta( $product_id, '_wcs_configurator_enabled', 'yes' );
	$local = get_post_meta( $option, '_wcs_option_data', true );
	$local['image'] = $image;
	$local['customer_fields'][0]['options'][0]['image'] = $image . '?left';
	$local['customer_fields'][0]['options'][1]['image'] = $image . '?right';
	update_post_meta( $option, '_wcs_option_data', $local );
	update_post_meta( $option, '_wcs_image', $image );
	$dependencies = get_post_meta( $target, '_wcs_template_dependencies', true );
	$dependencies['positions']['bluetooth'] = array( 'enabled' => true, 'options' => array( array( 'value' => 'top', 'label' => 'Oben', 'image' => $image . '?position' ) ) );
	update_post_meta( $target, '_wcs_template_dependencies', $dependencies );
	$cache->bump_version();
	$document['template']['status'] = 'draft';
	$document['template']['data']['slug'] = 'do-not-change-destination-slug';
	$document['template']['data']['dimensions']['min_width'] = 650;
	$document['positions']['bluetooth'] = array( 'enabled' => true, 'options' => array( array( 'value' => 'top', 'label' => 'Oben', 'image' => '' ) ) );
	$document['options'][0]['title'] .= ' renamed';
	$document['options'][0]['meta']['_wcs_option_data']['price'] = 25;
	$document['options'][0]['meta']['_wcs_price'] = 25;
	$document['options'][0]['meta']['_wcs_option_data']['customer_fields'][0]['options'] = array_reverse( $document['options'][0]['meta']['_wcs_option_data']['customer_fields'][0]['options'] );
	$document['options'][0]['meta']['_wcs_option_data']['customer_fields'][1]['min'] = 175;
	$before_count = count( $created );
	$result = $expect( $importer->import( wp_json_encode( $document ) ) );
	$updated = $templates->get_template_data( $target );
	$updated_option = get_post_meta( $option, '_wcs_option_data', true );
	$check( 'updated' === $result['action'] && $target === $result['template_id'] && count( $created ) === $before_count, 'Matching title updates in place without creating duplicate records' );
	$check( $updated['slug'] === $target_data['slug'] && 'publish' === get_post_status( $target ) && (int) get_post_meta( $product_id, '_wcs_template_id', true ) === $target, 'Existing slug, publication status and product assignment are preserved' );
	$check( 650 === $updated['dimensions']['min_width'] && 25 === $updated_option['price'] && 175 === $updated_option['customer_fields'][1]['min'], 'Dimensions, prices and nested customer settings are replaced' );
	$check( $updated_option['image'] === $image && get_post_meta( $option, '_wcs_image', true ) === $image && $updated_option['customer_fields'][0]['options'][0]['image'] === $image . '?right', 'Local images survive option renaming and dropdown reordering by stable keys' );
	$check( get_post_meta( $target, '_wcs_template_dependencies', true )['positions']['bluetooth']['options'][0]['image'] === $image . '?position', 'Local position images are preserved' );
	$check( strpos( $updated['validation_rules'][0]['conditions'][0]['path'], '__choice_' . $option . '.' ) !== false, 'Updated rules reference the reused destination option ID' );
	$payload = $expect( wcs_get_config_builder()->build_for_product( $product_id ) );
	$check( 650 === $payload['min_width'] && false !== strpos( wp_json_encode( $payload ), '175' ), 'Assigned product immediately exposes the updated configuration' );
	$selections = array( 'side_width' => 650, 'side_height' => 400, 'width' => 650, 'height' => 400, 'bluetooth__choice_' . $option => 'clock', 'bluetooth__choice_' . $option . '.clock.position' => 'right', 'bluetooth__choice_' . $option . '.clock.distance' => 175 );
	$priced = $expect( wcs_get_config_builder()->build_cart_configuration( $product_id, $selections ) );
	$check( $priced['price'] >= 128, 'Fresh cart configuration uses the updated option and dropdown prices' );
	$again = $expect( $importer->import( wp_json_encode( $document ) ) );
	$check( count( $created ) === $before_count && $again['template_id'] === $target, 'Repeated updates are idempotent for template and option IDs' );
	// A second layout using the same option must not be changed by this upload.
	$other = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_status' => 'publish', 'post_title' => $prefix . ' Other' ), true ) );
	$templates->save_template_data( $other, array( 'slug' => 'other', 'enabled_groups' => array( 'bluetooth' ), 'extra_option_map' => array( 'bluetooth' => array( $option ) ), 'choice_order' => array( $option ) ) );
	$old_shared = get_post_meta( $option );
	$document['options'][0]['meta']['_wcs_option_data']['price'] = 31;
	$expect( $importer->import( wp_json_encode( $document ) ) );
	$private_option = $templates->get_template_data( $target )['choice_order'][0];
	$check( $private_option !== $option && get_post_meta( $option ) === $old_shared && $templates->get_template_data( $other )['choice_order'] === array( $option ), 'Shared option is copied and the other layout remains unchanged' );
	$check( get_post_meta( $private_option, '_wcs_option_data', true )['image'] === $image, 'Private copy retains the destination image without creating media' );
	$expect( $importer->import( wp_json_encode( $document ) ) );
	$check( $templates->get_template_data( $target )['choice_order'] === array( $private_option ), 'Subsequent imports reuse the private copy' );
	// A failure after changing an existing option must restore the complete snapshot.
	$old_target = get_post_meta( $target );
	$old_private = get_post_meta( $private_option );
	$fail_once = true;
	$fail = static function ( $result, $id, $key ) use ( $target, &$fail_once ) {
		if ( $fail_once && $id === $target && '_wcs_template_data' === $key ) { $fail_once = false; return false; }
		return $result;
	};
	$document['template']['data']['dimensions']['min_width'] = 777;
	$document['options'][0]['meta']['_wcs_option_data']['price'] = 99;
	add_filter( 'update_post_metadata', $fail, 10, 3 );
	$failure = $importer->import( wp_json_encode( $document ) );
	remove_filter( 'update_post_metadata', $fail, 10 );
	$check( is_wp_error( $failure ) && get_post_meta( $target ) == $old_target && get_post_meta( $private_option ) == $old_private, 'Handled write failure restores the template and updated option metadata' );
	$document['template']['data']['dimensions']['min_width'] = 650;
	$expect( $importer->import( wp_json_encode( $document ) ) );
	$check( true, 'Failed import releases its lock so retry succeeds' );
	// Removed choices are detached; reintroduced private choices can be reused.
	$empty = $document;
	$empty['options'] = array();
	$empty['template']['data']['extra_option_map']['bluetooth'] = array();
	$empty['template']['data']['choice_order'] = array();
	$empty['template']['data']['choice_step_map'] = array();
	$empty['template']['data']['validation_rules'] = array();
	$empty['template']['data']['behavior_rules'] = array();
	$expect( $importer->import( wp_json_encode( $empty ) ) );
	$check( $templates->get_template_data( $target )['choice_order'] === array() && get_post( $private_option ), 'Omitted options are detached without deleting their catalog records' );
	$expect( $importer->import( wp_json_encode( $document ) ) );
	$check( $templates->get_template_data( $target )['choice_order'] === array( $private_option ), 'Reintroduced private option is reused without another copy' );
	update_post_meta( $private_option, '_wcs_legacy_id', 987 );
	$before_delete = get_post_meta( $private_option );
	$delete_once = true;
	$block_delete = static function ( $result, $id, $key ) use ( $private_option, &$delete_once ) {
		if ( $delete_once && $id === $private_option && '_wcs_legacy_id' === $key ) { $delete_once = false; return false; }
		return $result;
	};
	add_filter( 'delete_post_metadata', $block_delete, 10, 3 );
	$delete_failure = $importer->import( wp_json_encode( $document ) );
	remove_filter( 'delete_post_metadata', $block_delete, 10 );
	$check( is_wp_error( $delete_failure ) && get_post_meta( $private_option ) === $before_delete, 'Failure to remove obsolete metadata also rolls back the option' );
	$duplicate = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_status' => 'draft', 'post_title' => $document['template']['title'] ), true ) );
	$before = count( $created );
	$conflict = $importer->import( wp_json_encode( $document ) );
	$check( is_wp_error( $conflict ) && 'wcs_layout_ambiguous' === $conflict->get_error_code() && count( $conflict->get_error_data()['matches'] ) === 2 && count( $created ) === $before, 'Duplicate names require selecting a target before any post changes' );
	$expect( $importer->import( wp_json_encode( $document ), $target ) );
	$check( ! metadata_exists( 'post', $duplicate, '_wcs_template_data' ), 'Explicit target selection updates only the chosen matching template' );
	$check( is_wp_error( $importer->import( wp_json_encode( $document ), $source ) ), 'Selecting a nonmatching template is rejected' );
	$case = $document;
	$case['template']['title'] = '  ' . strtoupper( $document['template']['title'] ) . '  ';
	$check( 'wcs_layout_ambiguous' === $importer->import( wp_json_encode( $case ) )->get_error_code(), 'Name matching ignores case and surrounding whitespace' );
	wp_set_current_user( 0 );
	$check( is_wp_error( $importer->import( wp_json_encode( $document ), $target ) ), 'Unauthorized updates are rejected' );
	wp_set_current_user( $admins[0]->ID );
	// Synthetic imported groups must follow a private copy's destination ID too.
	$alias_document = $document;
	$alias_document['template']['title'] = $prefix . ' Synthetic';
	$alias = 'bluetooth__choice_' . $source_option;
	$alias_document['groups'] = array( $alias => $document['groups']['bluetooth'] );
	$alias_document['groups'][ $alias ]['slug'] = $alias;
	$alias_document['options'][0]['group'] = $alias;
	$alias_document['template']['data']['enabled_groups'] = array( $alias );
	$alias_document['template']['data']['group_order'] = array( $alias );
	$alias_document['template']['data']['step_map'] = array( $alias => 2 );
	$alias_document['template']['data']['extra_option_map'] = array( $alias => array( $source_option ) );
	$alias_document['positions'] = array( $alias => $document['positions']['bluetooth'] );
	$alias_target = $expect( $importer->import( wp_json_encode( $alias_document ) ) )['template_id'];
	$alias_settings = $templates->get_template_data( $alias_target );
	$alias_option = $alias_settings['choice_order'][0];
	$local_alias = 'bluetooth__choice_' . $alias_option;
	$check( $alias_settings['enabled_groups'] === array( $local_alias ) && isset( $alias_settings['extra_option_map'][ $local_alias ] ), 'Synthetic group names and selection-map keys use destination IDs' );
	$alias_dependencies = get_post_meta( $alias_target, '_wcs_template_dependencies', true );
	$alias_dependencies['positions'][ $local_alias ]['options'][0]['image'] = $image . '?synthetic';
	update_post_meta( $alias_target, '_wcs_template_dependencies', $alias_dependencies );
	$alias_other = wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => $prefix . ' Synthetic other', 'post_status' => 'publish' ) );
	update_post_meta( $alias_other, '_wcs_template_data', get_post_meta( $alias_target, '_wcs_template_data', true ) );
	update_post_meta( $alias_other, '_wcs_template_dependencies', $alias_dependencies );
	$cache->bump_version();
	$expect( $importer->import( wp_json_encode( $alias_document ) ) );
	$private_alias_settings = $templates->get_template_data( $alias_target );
	$new_alias_option = $private_alias_settings['choice_order'][0];
	$new_alias = 'bluetooth__choice_' . $new_alias_option;
	$check( $new_alias_option !== $alias_option && get_post_meta( $alias_target, '_wcs_template_dependencies', true )['positions'][ $new_alias ]['options'][0]['image'] === $image . '?synthetic', 'Private copying remaps synthetic groups while retaining their local position images' );
	$expect( $importer->import( wp_json_encode( $alias_document ) ) );
	$check( $templates->get_template_data( $alias_target )['choice_order'] === array( $new_alias_option ), 'Repeated synthetic-group updates reuse the private option' );
	$check( 0 === $http && ! array_filter( $created, static fn( int $id ): bool => 'attachment' === get_post_type( $id ) ), 'Settings updates perform no media requests or attachment creation' );
	echo "Completed $checks layout update checks.\n";
} finally {
	remove_filter( 'pre_http_request', $block );
	if ( isset( $fail ) ) { remove_filter( 'update_post_metadata', $fail, 10 ); }
	if ( isset( $block_delete ) ) { remove_filter( 'delete_post_metadata', $block_delete, 10 ); }
	remove_action( 'wp_insert_post', $track, 10 );
	foreach ( array_reverse( $created ) as $id ) { wp_delete_post( $id, true ); }
	$cache->bump_version();
}
