<?php
/**
 * Disposable development-site integration checks.
 * Usage: php tests/layout-transfer-integration.php /path/to/wp-load.php
 * Creates its own fixtures and deletes them in finally; does not edit existing content.
 *
 * @package WooSpiegelloftConfigurator
 */
declare(strict_types=1);

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) || ! is_file( $argv[1] ) ) {
	fwrite( STDERR, "Usage: php tests/layout-transfer-integration.php /path/to/wp-load.php\n" );
	exit( 1 );
}
define( 'WP_ADMIN', true );
require $argv[1];

$checks = 0;
$created = array();
$term_id = 0;
$old_post = $_POST;
$old_user = get_current_user_id();
$track = static function ( int $id, WP_Post $post, bool $update ) use ( &$created ): void {
	if ( ! $update ) {
		$created[ $id ] = $post->post_type;
	}
};
$check = static function ( bool $condition, string $message ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
};
$expect = static function ( $result ) {
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_code() . ': ' . $result->get_error_message() );
	}
	return $result;
};
add_action( 'wp_insert_post', $track, 10, 3 );
$track_attachment = static function ( int $id ) use ( &$created ): void {
	$created[ $id ] = 'attachment';
};
add_action( 'add_attachment', $track_attachment );
$http_calls = 0;
$block_http = static function () use ( &$http_calls ) {
	++$http_calls;
	return new WP_Error( 'test_no_http', 'HTTP disabled during settings-only transfer checks.' );
};
add_filter( 'pre_http_request', $block_http );
$exit_code = 0;
try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( ! $admins ) {
		throw new RuntimeException( 'A development-site administrator is required.' );
	}
	wp_set_current_user( $admins[0]->ID );
	$cache = new WCS_Cache();
	$template = new WCS_Template( $cache );
	$catalog = new WCS_Extras_Catalog( $cache );
	$registry = new WCS_Extras_Registry();
	$package = new WCS_Layout_Package();
	$exporter = new WCS_Layout_Exporter( $template, $registry, $catalog, $package );
	$importer = new WCS_Layout_Importer( $package, $cache );
	$builder = new WCS_Config_Builder( $registry, $catalog, $template, new WCS_Validation_Engine() );
	$group = array( 'slug' => 'transfer_fixture', 'label' => 'Transfer fixture', 'type' => 'selectable', 'optional_fields' => array( 'positions' => array( 'type' => 'repeater', 'fields' => array( array( 'id' => 'image', 'type' => 'image' ) ) ) ) );
	$empty_group = array( 'slug' => 'transfer_empty', 'label' => 'Empty fixture', 'type' => 'selectable', 'optional_fields' => array() );
	$group_filter = static function ( array $groups ) use ( $group, $empty_group ): array {
		return array( 'transfer_fixture' => $group, 'transfer_empty' => $empty_group ) + $groups;
	};
	add_filter( 'wcs_register_extra_groups', $group_filter );
	$registry->load_groups();
	$term = $expect( wp_insert_term( 'WCS Transfer Test ' . wp_generate_uuid4(), 'wcs_extra_group', array( 'slug' => 'transfer_fixture' ) ) );
	$term_id = (int) $term['term_id'];
	$image_url = 'https://example.invalid/source-image.png';
	$nested = array( array( 'key' => 'finish', 'label' => 'Finish', 'type' => 'dropdown', 'required' => true, 'options' => array( array( 'label' => 'Brass', 'value' => 'brass', 'price' => 12.5, 'image' => $image_url, 'nested_enabled' => true, 'customer_fields' => array( array( 'key' => 'engraving', 'label' => 'Engraving', 'type' => 'text', 'price' => 3, 'min' => '0', 'max' => '12', 'required' => true ) ) ) ) ) );
	$one = $expect( $catalog->save_option( array( 'title' => 'WCS transfer fixture one', 'slug' => 'fixture-one', 'group' => 'transfer_fixture', 'meta' => array( '_wcs_option_data' => array( 'name' => 'Fixture one', 'value' => 'fixture-one', 'price' => 25.75, 'image' => $image_url, 'required' => true, 'customer_fields' => $nested, 'positions' => array( array( 'image' => $image_url ) ) ), '_wcs_price' => '25.75', '_wcs_legacy_id' => 87654, '_wcs_image' => $image_url ) ) ) );
	$two = $expect( $catalog->save_option( array( 'title' => 'WCS transfer fixture two', 'slug' => 'fixture-two', 'meta' => array( '_wcs_option_data' => array( 'name' => 'Fixture two', 'value' => 'fixture-two', 'price' => 6, 'image' => '', 'customer_fields' => array( array( 'key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'min' => '1', 'max' => '10', 'price' => 2 ) ) ), '_wcs_price' => '6' ) ) ) );
	$layout = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => 'WCS transfer fixture layout', 'post_status' => 'publish' ), true ) );
	$rule = array( 'rule_type' => 'visibility', 'match' => 'all', 'conditions' => array( array( 'source' => 'nested', 'path' => 'transfer_fixture__choice_' . $one . '.finish', 'field' => 'value', 'type' => 'selection', 'operator' => 'equals', 'value' => 'brass' ) ), 'actions' => array( array( 'action' => 'set_max', 'target_type' => 'nested', 'target' => 'transfer_fixture__choice_' . $two . '.amount', 'target_value' => '', 'value' => '{transfer_fixture__choice_' . $one . '.finish} + 1' ) ) );
	$template->save_template_data( $layout, array( 'panel_template' => 'Fixture panel', 'slug' => 'wcs-transfer-test', 'type' => 'test', 'dimensions' => array( 'min_width' => 410, 'max_width' => 2000, 'min_height' => 520, 'max_height' => 1900 ), 'enabled_groups' => array( 'transfer_fixture', 'transfer_empty', 'edge' ), 'group_order' => array( 'transfer_fixture', 'transfer_empty', 'edge' ), 'extra_option_map' => array( 'transfer_fixture' => array( $two, $one ), 'transfer_empty' => array() ), 'choice_order' => array( $two, $one ), 'choice_step_map' => array( $two => 3, $one => 2 ), 'step_map' => array( 'transfer_fixture' => 2 ), 'validation_rules' => array( $rule ), 'behavior_rules' => array( $rule ) ) );
	$original_data = get_post_meta( $layout, '_wcs_template_data', true );
	$original_option = get_post_meta( $one, '_wcs_option_data', true );
	$original_count = count( $catalog->get_options_by_group( 'transfer_fixture' ) );
	$json = $expect( $exporter->export( $layout ) );
	$document = $expect( $package->decode( $json ) );
	$check( 2 === count( $document['options'] ) && ! isset( $document['media'] ), 'Export captures selected/uncategorized options without a media section' );
	$check( 2 === $document['version'] && false === strpos( $json, $image_url ), 'New exports use schema version 2 and contain no source image URLs' );
	$imported = $expect( $importer->import( $json ) );
	$check( 2 === $imported['options'] && ! isset( $imported['images'] ), 'Import creates a template and options without media processing' );
	$new_id = $imported['template_id'];
	$new_data = $template->get_template_data( $new_id );
	list( $new_two, $new_one ) = $new_data['extra_option_map']['transfer_fixture'];
	$check( $new_one !== $one && $new_two !== $two && $new_data['choice_order'] === array( $new_two, $new_one ) && 3 === $new_data['choice_step_map'][ $new_two ], 'Option IDs, ordering and step-map keys are remapped' );
	$check( 'transfer_fixture__choice_' . $new_one . '.finish' === $new_data['validation_rules'][0]['conditions'][0]['path'] && false !== strpos( $new_data['behavior_rules'][0]['actions'][0]['value'], '__choice_' . $new_one . '.' ), 'Nested rule paths and formula references are remapped' );
	$check( 87654 === (int) get_post_meta( $new_one, '_wcs_legacy_id', true ) && $new_data['dimensions'] === $template->get_template_data( $layout )['dimensions'], 'Dimensions and legacy IDs survive import' );
	$new_option = get_post_meta( $new_one, '_wcs_option_data', true );
	$check( '' === $new_option['image'] && $new_option['customer_fields'][0]['options'][0]['image'] === $new_option['image'] && $new_option['positions'][0]['image'] === $new_option['image'], 'Top-level, nested and legacy image fields are cleared' );
	$check( $new_option['customer_fields'][0]['options'][0]['customer_fields'][0]['required'] && 25.75 === $new_option['price'], 'Nested fields, required flags and prices survive import' );
	$cache->bump_version();
	$check( $original_count === count( $catalog->get_options_by_group( 'transfer_fixture' ) ) && $original_data === get_post_meta( $layout, '_wcs_template_data', true ) && $original_option === get_post_meta( $one, '_wcs_option_data', true ), 'Existing content and automatic category membership remain unchanged' );
	$again = $expect( $importer->import( $json ) );
	$check( $again['template_id'] !== $new_id && $template->get_template_data( $again['template_id'] )['slug'] !== $new_data['slug'], 'Repeated imports create independent copies with unique slugs' );
	$check( ! is_wp_error( $exporter->export( $new_id ) ), 'Imported layouts can be exported again' );
	$draft_document = $document;
	$draft_document['template']['status'] = 'draft';
	$draft_result = $expect( $importer->import( wp_json_encode( $draft_document ) ) );
	$check( 'draft' === get_post_status( $draft_result['template_id'] ), 'Draft status is preserved' );
	$product = new WC_Product_Simple();
	$product->set_name( 'WCS transfer fixture product' );
	$product->set_regular_price( '100' );
	$product_id = $product->save();
	$template->set_product_template_id( $product_id, $new_id );
	$payload = $expect( $builder->build_for_product( $product_id ) );
	$check( false !== strpos( wp_json_encode( $payload ), 'Fixture one' ), 'Assigned product builds storefront configuration with imported choices' );
	$check( false === strpos( wp_json_encode( $payload ), 'transfer_empty__choice_' ), 'Empty imported groups do not load destination options' );
	$legacy = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => 'WCS transfer legacy fixture', 'post_status' => 'publish' ), true ) );
	$template->save_template_data( $legacy, array( 'panel_template' => 'Legacy', 'slug' => 'legacy-transfer', 'enabled_groups' => array( 'transfer_fixture', 'edge' ), 'extra_option_map' => array(), 'group_order' => array( 'transfer_fixture', 'edge' ) ) );
	$legacy_document = $expect( $package->decode( $expect( $exporter->export( $legacy ) ) ) );
	$check( array( $one ) === $legacy_document['template']['data']['extra_option_map']['transfer_fixture'], 'Implicit all-options groups export their effective selection explicitly' );
	$legacy_document['positions']['transfer_fixture'] = array( 'enabled' => true, 'label' => 'Fixture position', 'show_when' => '', 'options' => array( array( 'title' => 'Left', 'image' => 'https://example.invalid/position.png' ) ) );
	$legacy_import = $expect( $importer->import( wp_json_encode( $legacy_document ) ) );
	$check( isset( $template->get_position_settings( $legacy_import['template_id'] )['transfer_fixture']['options'][0]['image'] ), 'Position settings import as template-local snapshots' );
	$legacy_data = $template->get_template_data( $legacy_import['template_id'] );
	$legacy_payload = $builder->build_payload( $product, $legacy_data );
	$check( false !== strpos( wp_json_encode( $legacy_payload ), 'Fixture position' ), 'Storefront consumes imported position settings' );
	$static_document = $legacy_document;
	$static_document['options'] = array();
	$static_document['media'] = array();
	$static_document['positions'] = array();
	$static_document['groups'] = array( 'edge' => $legacy_document['groups']['edge'] );
	$static_document['template']['data']['enabled_groups'] = array( 'edge' );
	$static_document['template']['data']['group_order'] = array( 'edge' );
	$static_document['template']['data']['extra_option_map'] = array();
	$static_import = $expect( $importer->import( wp_json_encode( $static_document ) ) );
	$check( 0 === $static_import['options'] && ! isset( $static_import['images'] ), 'Static-only layouts import without customer options or media' );
	// Reproduce a flat editor with checked, uncategorized choices but no saved map.
	$flat_catalog = new class( $cache ) extends WCS_Extras_Catalog {
		public array $fixture_options = array();
		public function get_all_options(): array {
			return $this->fixture_options;
		}
	};
	$flat_catalog->fixture_options = array( $catalog->get_option( $one ), $catalog->get_option( $two ) );
	$flat_exporter = new WCS_Layout_Exporter( $template, $registry, $flat_catalog, $package );
	$flat_layout = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => 'WCS transfer implicit flat fixture', 'post_status' => 'publish' ), true ) );
	$template->save_template_data( $flat_layout, array( 'panel_template' => 'Implicit flat', 'slug' => 'implicit-flat', 'enabled_groups' => array( 'suspension_plates' ), 'group_order' => array( 'suspension_plates' ), 'choice_order' => array( $two, $one ), 'choice_step_map' => array( $two => 1, $one => 1 ), 'extra_option_map' => array(), 'step_map' => array( 'suspension_plates' => 1 ) ) );
	$flat_data = $template->get_template_data( $flat_layout );
	$flat_admin = new WCS_Template_Admin( $template, $registry, $flat_catalog );
	ob_start();
	$flat_admin->render_choices_meta_box( get_post( $flat_layout ) );
	$flat_html = ob_get_clean();
	$check( 2 === preg_match_all( '/type="checkbox"[^>]*checked=/', $flat_html ), 'Legacy flat editor checks its effective options even without category membership' );
	$flat_document = $expect( $package->decode( $expect( $flat_exporter->export( $flat_layout ) ) ) );
	$check( 2 === count( $flat_document['options'] ) && ! isset( $flat_document['media'] ) && array( $two, $one ) === $flat_document['template']['data']['extra_option_map']['suspension_plates'], 'Legacy flat export includes checked options, order and steps without media' );
	$flat_result = $expect( $importer->import( wp_json_encode( $flat_document ) ) );
	$check( 2 === $flat_result['options'] && ! isset( $flat_result['images'] ), 'Legacy flat import creates its actual customer options without media' );
	$flat_builder = new WCS_Config_Builder( $registry, $flat_catalog, $template, new WCS_Validation_Engine() );
	$flat_payload = $flat_builder->build_payload( $product, $flat_data );
	$check( false !== strpos( wp_json_encode( $flat_payload ), 'Fixture one' ), 'Legacy flat storefront uses the same selection as export and editor' );
	$_POST = array( 'wcs_template_nonce' => wp_create_nonce( 'wcs_save_template' ), 'wcs_template_slug' => $flat_data['slug'], 'wcs_template_type' => $flat_data['type'], 'wcs_side_measurements' => $flat_data['dimensions']['side_measurements'], 'wcs_enabled_groups' => array( 'suspension_plates' ), 'wcs_group_order' => array( 'suspension_plates' ), 'wcs_group_steps' => array( 'suspension_plates' => 1 ), 'wcs_choice_order' => array( $two, $one ), 'wcs_choice_steps' => array( $two => 1, $one => 1 ) );
	$flat_admin->save_template( $flat_layout, get_post( $flat_layout ) );
	$_POST = array();
	ob_start();
	$flat_admin->render_choices_meta_box( get_post( $flat_layout ) );
	$empty_html = ob_get_clean();
	$check( 0 === preg_match_all( '/type="checkbox"[^>]*checked=/', $empty_html ), 'Unchecking every choice stays empty after saving and reopening' );
	$empty_document = $expect( $package->decode( $expect( $flat_exporter->export( $flat_layout ) ) ) );
	$check( 0 === count( $empty_document['options'] ) && ! isset( $empty_document['media'] ), 'Explicit empty selection exports no unrelated catalog choices or images' );
	$old_document = $document;
	$old_document['version'] = 1;
	$old_document['media'] = array( 'ignored' => array( 'data' => 'not-even-valid-base64', 'filename' => 'ignored.php' ) );
	$old_document['groups']['transfer_fixture']['optional_fields']['preview'] = array( 'id' => 'preview', 'type' => 'image', 'default' => 'https://example.invalid/default.png' );
	$old_document['options'][0]['meta']['_wcs_option_data']['image'] = 'wcs-media:ignored';
	$old_document['options'][0]['meta']['_wcs_option_data']['preview'] = 'https://example.invalid/preview.png';
	$old_document['options'][0]['meta']['_wcs_option_data']['customer_fields'][0]['options'][0]['image'] = 'https://example.invalid/nested.png';
	$before_attachment_count = wp_count_posts( 'attachment' )->inherit;
	$old_result = $expect( $importer->import( wp_json_encode( $old_document ) ) );
	$old_data = $template->get_template_data( $old_result['template_id'] );
	$old_option_id = $old_data['extra_option_map']['transfer_fixture'][0];
	$old_option = get_post_meta( $old_option_id, '_wcs_option_data', true );
	$check( '' === $old_option['image'] && '' === $old_option['preview'] && '' === $old_option['customer_fields'][0]['options'][0]['image'], 'Older packages ignore embedded media, source URLs and custom image fields' );
	$check( $before_attachment_count === wp_count_posts( 'attachment' )->inherit, 'Import creates no Media Library attachments' );
	$old_groups = $registry->get_groups_for_template( $old_result['template_id'] );
	$check( 'image' === $old_groups['transfer_fixture']['optional_fields']['preview']['type'] && '' === $old_groups['transfer_fixture']['optional_fields']['preview']['default'], 'Image field definitions remain available for manual uploads without source defaults' );
	$version_one = $document;
	$version_one['version'] = 1;
	$check( ! is_wp_error( $package->decode( wp_json_encode( $version_one ) ) ), 'Version 1 JSON without a media section remains compatible' );
	$bad = $document;
	$bad['version'] = 999;
	$check( is_wp_error( $importer->import( wp_json_encode( $bad ) ) ), 'Unsupported schema is rejected' );
	$check( is_wp_error( $importer->import( '{broken' ) ), 'Malformed JSON is rejected' );
	$bad = $document;
	$bad['template']['data']['extra_option_map']['transfer_fixture'][] = 999999999;
	$check( is_wp_error( $importer->import( wp_json_encode( $bad ) ) ), 'Missing dependencies are rejected before writes' );
	$bad = $document;
	$bad['options'][1]['meta']['_wcs_option_data']['customer_fields'][0]['type'] = 'unsupported';
	$check( is_wp_error( $importer->import( wp_json_encode( $bad ) ) ), 'Unsupported customer field types are rejected' );
	$bad = $document;
	$bad['groups']['transfer_fixture']['optional_fields']['positions']['type'] = 'unknown';
	$check( is_wp_error( $importer->import( wp_json_encode( $bad ) ) ), 'Unsupported legacy group field types are rejected' );
	$bad = $document;
	$bad['template']['data']['validation_rules'][0]['conditions'][0]['path'] = 'transfer_fixture__choice_99999999.finish';
	$check( is_wp_error( $importer->import( wp_json_encode( $bad ) ) ), 'Unresolved rule references are rejected before writes' );
	$check( is_wp_error( $package->decode( str_repeat( ' ', WCS_Layout_Package::MAX_BYTES + 1 ) ) ), 'Oversized JSON is rejected' );
	$check( is_wp_error( $package->decode( str_repeat( '[', 40 ) . '0' . str_repeat( ']', 40 ) ) ), 'Excessive nesting is rejected' );
	$failure_filter = static function ( array $data ): array {
		if ( 'wcs_template' === ( $data['post_type'] ?? '' ) ) {
			throw new RuntimeException( 'Simulated template insert failure' );
		}
		return $data;
	};
	$before = array_keys( $created );
	add_filter( 'wp_insert_post_data', $failure_filter );
	$failure = $importer->import( $json );
	remove_filter( 'wp_insert_post_data', $failure_filter );
	$rolled_back = array_diff( array_keys( $created ), $before );
	$check( is_wp_error( $failure ) && count( $rolled_back ) >= 2 && ! array_filter( $rolled_back, 'get_post' ), 'Handled failure removes new options: ' . wp_json_encode( array( 'result' => is_wp_error( $failure ) ? $failure->get_error_message() : $failure, 'created' => $rolled_back, 'remaining' => array_filter( $rolled_back, 'get_post' ) ) ) );
	$field_registry = new WCS_Extra_Field_Type_Registry();
	$field_registry->register_defaults();
	$choice_admin = new WCS_Choice_Meta( $registry, $field_registry );
	$_POST = array( 'wcs_choice_nonce' => wp_create_nonce( 'wcs_save_choice' ), 'wcs_option_value' => $new_option['value'], 'wcs_price' => $new_option['price'], 'wcs_required' => '1', 'wcs_legacy_id' => 87654, 'wcs_customer_fields' => wp_slash( $new_option['customer_fields'] ) );
	$choice_admin->save_meta_boxes( $new_one, get_post( $new_one ) );
	$check( get_post_meta( $new_one, '_wcs_option_data', true )['image'] === $new_option['image'], 'Customer option editor saves preserve legacy images' );
	$_POST = array( 'wcs_template_nonce' => wp_create_nonce( 'wcs_save_template' ), 'wcs_template_slug' => $new_data['slug'], 'wcs_template_type' => $new_data['type'], 'wcs_side_measurements' => $new_data['dimensions']['side_measurements'], 'wcs_enabled_groups' => array( 'transfer_fixture' ), 'wcs_group_order' => array( 'transfer_fixture' ), 'wcs_group_steps' => array( 'transfer_fixture' => 2 ), 'wcs_extra_option_map' => array( 'transfer_fixture' => array( $new_two, $new_one ) ), 'wcs_choice_order' => array( $new_two, $new_one ), 'wcs_choice_steps' => array( $new_two => 3, $new_one => 2 ), 'wcs_validation_rules' => wp_slash( $new_data['validation_rules'] ) );
	$template_admin = new WCS_Template_Admin( $template, $registry, $catalog );
	$template_admin->save_template( $new_id, get_post( $new_id ) );
	$after_save = $template->get_template_data( $new_id );
	$check( $after_save['enabled_groups'] === $new_data['enabled_groups'] && $after_save['dimensions'] === $new_data['dimensions'] && $after_save['panel_template'] === $new_data['panel_template'], 'Layout editor saves preserve group structure, dimensions and panel settings' );
	$admin = new WCS_Layout_Transfer_Admin( $exporter, $importer );
	$actions = $admin->row_actions( array(), get_post( $new_id ) );
	$check( isset( $actions['wcs_export'] ) && false !== strpos( $actions['wcs_export'], '_wpnonce=' ), 'Listing export has a per-layout nonce' );
	ob_start();
	$admin->render_panel( get_post( $new_id ) );
	$panel = ob_get_clean();
	$check( false !== strpos( $panel, 'Import Layout' ) && false !== strpos( $panel, 'Export JSON' ), 'Individual layout page exposes both controls' );
	$draft = $expect( wp_insert_post( array( 'post_type' => 'wcs_template', 'post_status' => 'auto-draft' ), true ) );
	ob_start();
	$admin->render_panel( get_post( $draft ) );
	$panel = ob_get_clean();
	$check( false !== strpos( $panel, 'disabled' ) && is_wp_error( $exporter->export( $draft ) ), 'Unsaved exports are disabled and rejected' );
	wp_set_current_user( 0 );
	$check( ! isset( $admin->row_actions( array(), get_post( $new_id ) )['wcs_export'] ), 'Unauthorized users receive no export action' );
	$check( 0 === $http_calls, 'Settings-only transfer makes no HTTP image requests' );
	echo 'Completed ' . $checks . ' integration checks.' . PHP_EOL;
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL );
	$exit_code = 1;
} finally {
	$_POST = $old_post;
	wp_set_current_user( $old_user );
	remove_action( 'wp_insert_post', $track );
	remove_action( 'add_attachment', $track_attachment );
	remove_filter( 'pre_http_request', $block_http );
	if ( isset( $failure_filter ) ) {
		remove_filter( 'wp_insert_post_data', $failure_filter );
	}
	foreach ( array_reverse( $created, true ) as $id => $type ) {
		if ( get_post( $id ) ) {
			'attachment' === $type ? wp_delete_attachment( $id, true ) : wp_delete_post( $id, true );
		}
	}
	if ( $term_id ) {
		wp_delete_term( $term_id, 'wcs_extra_group' );
	}
	if ( isset( $group_filter ) ) {
		remove_filter( 'wcs_register_extra_groups', $group_filter );
	}
	( new WCS_Cache() )->bump_version();
}
exit( $exit_code );
