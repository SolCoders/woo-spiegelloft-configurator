<?php
/** Isolated checks for AJAX error transport and session notice preservation. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/includes/class-wcs-storefront-text.php';
class WCS_Validation_Engine {}
class Feedback_Response extends Exception {
	public function __construct( public bool $success, public array $data, public int $status = 200 ) {}
}
function __( $text, $domain ) { return $text; }
function add_filter( ...$args ) {}
function remove_filter( ...$args ) {}
function check_ajax_referer( ...$args ) { return $GLOBALS['nonce_valid']; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wc_stock_amount( $value ) { return (int) $value; }
function get_post_meta( ...$args ) { return 'yes'; }
function wc_get_notices() { return $GLOBALS['notices']; }
function wc_set_notices( $notices ) { $GLOBALS['notices'] = $notices; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_send_json_error( $data, $status = 200 ) { throw new Feedback_Response( false, $data, $status ); }
function wp_send_json_success( $data ) { throw new Feedback_Response( true, $data ); }
function apply_filters( $name, $value, ...$args ) {
	if ( 'woocommerce_add_to_cart_validation' === $name && 'validation' === $GLOBALS['scenario'] ) {
		$GLOBALS['notices']['error'][] = array( 'notice' => '<strong>Invalid measurement</strong>' );
		$GLOBALS['notices']['notice'][] = array( 'notice' => 'Choose a compatible option' );
		return false;
	}
	return $value;
}
function do_action( ...$args ) {
	if ( 'warning' === $GLOBALS['scenario'] ) {
		$GLOBALS['notices']['notice'][] = array( 'notice' => 'Please check the delivery details' );
	}
}
function WC() { return $GLOBALS['woocommerce']; }
function woocommerce_mini_cart() { echo 'Configured product'; }
function get_option( $name ) { return 'no'; }
function wc_get_cart_url() { return '/cart/'; }
require dirname( __DIR__ ) . '/includes/woocommerce/class-wcs-cart.php';
$GLOBALS['woocommerce'] = (object) array( 'cart' => new class {
	public int $calls = 0;
	public function add_to_cart( ...$args ) {
		$this->calls++;
		if ( 'exception' === $GLOBALS['scenario'] ) { throw new Exception( 'Cart request failed' ); }
		if ( 'stock' === $GLOBALS['scenario'] ) {
			$GLOBALS['notices']['error'][] = array( 'notice' => 'Not enough stock' );
			return false;
		}
		return 'cart-key';
	}
	public function get_cart_hash() { return 'hash'; }
} );
$check = static function ( bool $valid, string $label ): void {
	if ( ! $valid ) { throw new RuntimeException( $label ); }
	echo 'PASS: ' . $label . PHP_EOL;
};
$cart = new WCS_Cart( new WCS_Validation_Engine() );
foreach ( array( 'nonce', 'validation', 'stock', 'exception', 'warning', 'success' ) as $scenario ) {
	$GLOBALS['scenario'] = $scenario;
	$GLOBALS['nonce_valid'] = 'nonce' !== $scenario;
	$previous = array( 'notice' => array( array( 'notice' => 'Unrelated existing notice' ) ) );
	wc_set_notices( $previous );
	$_POST = array( 'product_id' => 42, 'quantity' => 1, 'nonce' => 'test' );
	$before = WC()->cart->calls;
	try { $cart->ajax_add_to_cart(); throw new RuntimeException( 'No JSON response' ); }
	catch ( Feedback_Response $response ) {
		$check( wc_get_notices() === $previous, $scenario . ': restores prior notices without queuing popup messages' );
		$check( $response->success === in_array( $scenario, array( 'warning', 'success' ), true ), $scenario . ': correct response status' );
		if ( 'success' !== $scenario ) {
			$check( ! empty( $response->data['messages'] ), $scenario . ': provides popup messages' );
		}
		if ( in_array( $scenario, array( 'nonce', 'validation' ), true ) ) {
			$check( WC()->cart->calls === $before, $scenario . ': rejects before changing cart' );
		}
		if ( 'validation' === $scenario ) {
			$check( $response->data['messages'] === array( 'Invalid measurement', 'Choose a compatible option' ), 'All validation warnings and errors are returned as plain text' );
		}
		if ( $response->success ) {
			$check( ! empty( $response->data['fragments'] ) && 'hash' === $response->data['cart_hash'], $scenario . ': returns updated cart fragments' );
		}
	}
}
