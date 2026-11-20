<?php
/**
 * Local-only fixture and short-lived authentication for the browser smoke test.
 * Authentication is consumed privately by the Node runner, never logged.
 *
 * @package WooSpiegelloftConfigurator
 */
declare(strict_types=1);

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) || ! is_file( $argv[1] ) ) {
	exit( 1 );
}
require $argv[1];
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	fwrite( STDERR, "Browser fixtures are restricted to localhost development sites.\n" );
	exit( 1 );
}
$mode = $argv[2] ?? 'setup';
if ( 'cleanup' === $mode || 'assign' === $mode ) {
	$data = json_decode( stream_get_contents( STDIN ), true );
	$ids = array( $data['layout_id'], $data['option_id'], $data['product_id'] );
	if ( 'cleanup' === $mode ) {
		$cart_key = '_woocommerce_persistent_cart_' . get_current_blog_id();
		if ( ! empty( $data['cart_existed'] ) ) { update_user_meta( $data['user_id'], $cart_key, $data['cart_backup'] ); } else { delete_user_meta( $data['user_id'], $cart_key ); }
	}
	$imports = $data['imports'] ?? array();
	if ( 'cleanup' === $mode ) {
		$imports = array_merge( $imports, get_posts( array( 'post_type' => 'wcs_template', 'post_status' => 'any', 'title' => $data['marker'], 'fields' => 'ids', 'posts_per_page' => -1 ) ) );
	}
	foreach ( array_unique( $imports ) as $id ) {
		$post = get_post( $id );
		if ( $post && $data['marker'] === $post->post_title && 'wcs_template' === $post->post_type ) {
			if ( 'assign' === $mode ) {
				update_post_meta( $data['product_id'], '_wcs_template_id', (int) $id );
				echo wp_json_encode( array( 'ok' => true ) );
				exit;
			}
			$settings = get_post_meta( $id, '_wcs_template_data', true );
			foreach ( $settings['extra_option_map'] ?? array() as $options ) {
				$ids = array_merge( $ids, $options );
			}
			$ids[] = $id;
		}
	}
	foreach ( array_unique( $ids ) as $id ) {
		$post = get_post( $id );
		if ( $post && 0 === strpos( $post->post_title, $data['marker'] ) ) {
			wp_delete_post( $id, true );
		}
	}
	WP_Session_Tokens::get_instance( $data['user_id'] )->destroy( $data['token'] );
	( new WCS_Cache() )->bump_version();
	echo wp_json_encode( array( 'ok' => true ) );
	exit;
}
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! $admins ) {
	exit( 1 );
}
$user_id = (int) $admins[0]->ID;
wp_set_current_user( $user_id );
$cache = new WCS_Cache();
$catalog = new WCS_Extras_Catalog( $cache );
$template = new WCS_Template( $cache );
$marker = 'WCS Browser Fixture ' . wp_generate_uuid4();
$option_id = $catalog->save_option( array( 'title' => $marker . ' Option', 'slug' => 'browser-fixture', 'meta' => array( '_wcs_option_data' => array( 'name' => 'Browser fixture option', 'value' => 'browser-fixture', 'price' => 15, 'required' => true, 'customer_fields' => array( array( 'key' => 'finish', 'label' => 'Surface finish', 'type' => 'dropdown', 'options' => array( array( 'label' => 'Polished brass', 'value' => 'brass', 'price' => 5, 'image' => '' ) ) ) ) ) ) ) );
$layout_id = wp_insert_post( array( 'post_type' => 'wcs_template', 'post_title' => $marker, 'post_status' => 'publish' ), true );
if ( is_wp_error( $option_id ) || is_wp_error( $layout_id ) ) {
	fwrite( STDERR, "Unable to create browser fixtures.\n" );
	exit( 1 );
}
$template->save_template_data( $layout_id, array( 'panel_template' => $marker, 'slug' => 'wcs-browser-fixture', 'type' => 'Fixture', 'enabled_groups' => array( 'bluetooth' ), 'group_order' => array( 'bluetooth' ), 'extra_option_map' => array( 'bluetooth' => array( $option_id ) ), 'choice_order' => array( $option_id ), 'choice_step_map' => array( $option_id => 2 ) ) );
$product = new WC_Product_Simple();
$product->set_name( $marker . ' Product' );
$product->set_regular_price( '100' );
$product->set_status( 'publish' );
$product_id = $product->save();
$template->set_product_template_id( $product_id, $layout_id );
update_post_meta( $product_id, '_wcs_enabled', 'yes' );
update_post_meta( $product_id, '_wcs_configurator_enabled', 'yes' );
$expires = time() + 600;
$token = WP_Session_Tokens::get_instance( $user_id )->create( $expires );
echo wp_json_encode( array( 'cart_existed' => metadata_exists( 'user', $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id() ), 'cart_backup' => get_user_meta( $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true ), 'marker' => $marker, 'user_id' => $user_id, 'token' => $token, 'layout_id' => $layout_id, 'option_id' => $option_id, 'product_id' => $product_id, 'product_url' => get_permalink( $product_id ), 'base' => home_url( '/' ), 'cookies' => array( array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user_id, $expires, 'auth', $token ) ), array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user_id, $expires, 'logged_in', $token ) ) ) ) );
