<?php
/** Isolated display regressions. Run: php tests/german-cart.php */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
class WCS_Validation_Engine {}
class WCS_Cache {}
class WCS_Extras_Registry { public function load_groups() {} }
class WCS_Extras_Catalog { public function __construct( ...$args ) {} }
class WCS_Template { public function __construct( ...$args ) {} }
class WCS_Config_Builder {
	public function __construct( ...$args ) {}
	public function build_for_product( $id ) { return $GLOBALS['config']; }
}
class WCS_Pricing { public static function field_key( array $field ): string { return 'side_' . $field['key']; } }
class WC_Order {}
class WC_Order_Item_Product {
	public array $meta = array();
	public function add_meta_data( $key, $value, $unique ) { $this->meta[ $key ] = $value; }
}
function __( $text, $domain ) { return $text; }
function is_wp_error( $value ) { return false; }
function esc_html( $value ) { return htmlspecialchars( (string) $value ); }
function wc_price( $value ) { return '€' . number_format( $value, 2 ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
require dirname( __DIR__ ) . '/includes/class-wcs-storefront-text.php';
require dirname( __DIR__ ) . '/includes/woocommerce/class-wcs-cart.php';
require dirname( __DIR__ ) . '/includes/woocommerce/class-wcs-order.php';
$checks = 0;
$check = static function ( bool $valid, string $label ) use ( &$checks ): void {
	if ( ! $valid ) { throw new RuntimeException( $label ); }
	++$checks;
	echo 'PASS: ' . $label . PHP_EOL;
};
$config = array(
	'side_measurements' => array( array( 'key' => 'height', 'label' => 'Height' ) ),
	'extras' => array(
		'mounting' => array( 'title' => 'Montage', 'value' => array( array( 'value' => 'parent', 'name' => 'Montage', 'customer_fields' => array(
			array( 'key' => 'note', 'label' => 'Hinweis', 'type' => 'text' ),
			array( 'key' => 'distance', 'label' => 'Abstand', 'type' => 'number' ),
			array( 'key' => 'finish', 'label' => 'Oberfläche', 'type' => 'dropdown', 'options' => array( array( 'value' => 'brass', 'label' => 'Messing', 'price' => 0 ), array( 'value' => 'silver', 'label' => 'Silber', 'price' => 0 ) ) ),
		) ) ) ),
		'light' => array( 'title' => 'Lichtfarbe', 'value' => array( array( 'value' => 'warm', 'name' => 'Warmweiß', 'price' => 0 ) ) ),
	),
);
$cart = new WCS_Cart( new WCS_Validation_Engine() );
$build = new ReflectionMethod( $cart, 'build_display_items' );
$base = array( 'width' => 400, 'side_height' => 500, 'mounting' => 'parent' );
$items = $build->invoke( $cart, 1, $base + array( 'mounting.parent.note' => '   ', 'mounting.parent.finish' => 'none' ) );
$check( array_column( $items, 'label' ) === array( 'Breite', 'Höhe' ), 'Blank and placeholder children do not create parent cart rows' );
$items = $build->invoke( $cart, 1, $base + array( 'mounting.parent.distance' => '0', 'light' => 'warm' ) );
$check( in_array( '0', array_column( $items, 'value' ), true ), 'Explicit zero remains filled' );
$check( ! in_array( 'Montage', array_column( $items, 'label' ), true ), 'Hidden parent labels are omitted even when their customer fields are filled' );
$check( in_array( 'Warmweiß', array_column( $items, 'value' ), true ), 'Free selected options remain visible' );
$items = $build->invoke( $cart, 1, $base + array( 'mounting.parent.finish' => 'brass', 'mounting.parent.note' => 'Kundentext' ) );
$check( in_array( 'Messing', array_column( $items, 'value' ), true ), 'Dropdown uses its readable value' );
$check( in_array( 'Kundentext', array_column( $items, 'value' ), true ), 'Customer text is preserved' );
$old_cart = array( 'product_id' => 1, 'wcs_configuration' => array(
	'price' => 123.45, 'selections' => $base + array( 'mounting.parent.distance' => '0' ),
	'items' => array( array( 'label' => 'Montage', 'value' => 'Montage', 'price' => 10 ), array( 'label' => 'Abstand', 'value' => '0', 'price' => 0 ) ),
) );
$old_display = $cart->display_cart_item_data( array(), $old_cart );
$check( count( $old_display ) === 1 && 'Abstand' === $old_display[0]['key'], 'Already saved carts omit internal parent rows and retain the actual field' );
$restored = $cart->get_cart_item_from_session( array( 'product_id' => 1 ), $old_cart );
$check( count( $restored['wcs_configuration']['items'] ) === 1 && 123.45 === $restored['wcs_configuration']['price'], 'Restored cart and checkout use clean attributes without changing the saved price' );
$snapshot = array( 'selections' => $base, 'items' => array(
	array( 'label' => 'Width', 'value' => '400 mm' ), array( 'label' => 'Leer', 'value' => 'None' ),
	array( 'label' => 'Leer', 'value' => '---' ), array( 'label' => 'Leer', 'value' => '  ' ), array( 'label' => 'Abstand', 'value' => '0' ),
) );
$display = $cart->display_cart_item_data( array(), array( 'wcs_configuration' => $snapshot ) );
$check( count( $display ) === 2 && 'Breite' === $display[0]['key'], 'Existing cart snapshots hide empty rows and translate dimension labels' );
$order_item = new WC_Order_Item_Product();
( new WCS_Order() )->add_order_item_meta( $order_item, '', array( 'wcs_configuration' => $snapshot ), new WC_Order() );
$check( isset( $order_item->meta['Breite'], $order_item->meta['Abstand'] ) && ! isset( $order_item->meta['Leer'] ), 'Order attributes use German labels and omit empty values' );
$translated = WCS_Storefront_Text::config( array( 'key' => 'width', 'label' => 'Width', 'value' => 'warm', 'message' => 'Width is outside this product range.' ) );
$check( 'width' === $translated['key'] && 'warm' === $translated['value'] && 'Breite' === $translated['label'], 'Translations preserve stored keys and option values' );
$check( str_contains( $translated['message'], 'zulässigen Bereichs' ), 'Persisted English default rules display German messages' );
$check( str_contains( WCS_Storefront_Text::woocommerce_message( '', 'Sorry, this product cannot be purchased.', 'woocommerce' ), 'gekauft' ), 'WooCommerce purchase feedback is German within configurator submission' );
echo "Completed $checks German cart checks.\n";
