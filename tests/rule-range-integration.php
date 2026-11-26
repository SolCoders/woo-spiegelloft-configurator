<?php
/** Read-only checks for storefront/server formula parity. */
declare(strict_types=1);
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
require $argv[1];
$count = 0;
$check = static function ( bool $valid, string $message ) use ( &$count ): void {
	if ( ! $valid ) { throw new RuntimeException( $message ); }
	++$count;
	echo 'PASS: ' . $message . PHP_EOL;
};
$engine = new WCS_Validation_Engine();
$context = new ReflectionMethod( $engine, 'build_formula_context' );
$validate = new ReflectionMethod( $engine, 'validate_range_rule' );
$template = array( 'dimensions' => array( 'min_width' => 400, 'max_width' => 2500 ) );
$rule = array( 'target' => 'shelf.length', 'max' => '{width} - 100', 'message' => 'Glass shelf length must fit inside the mirror width.' );
$values = array( 'side_width' => 400, 'side_height' => 500, 'shelf.length' => 300 );
$data = $context->invoke( $engine, $values, $template );
$check( $data['width'] === 400.0 && $data['height'] === 500.0, 'Sidewise measurements expose the same formula aliases as the storefront' );
$check( true === $validate->invoke( $engine, $values, $template, $rule ), 'Corrected length of 300 passes width-minus-100 validation' );
$values['shelf.length'] = 301;
$error = $validate->invoke( $engine, $values, $template, $rule );
$check( is_wp_error( $error ) && $error->get_error_message() === $rule['message'], 'Length above 300 is rejected with the configured message' );
$values['shelf.length'] = 250;
$check( true === $validate->invoke( $engine, $values, $template, $rule ), 'A length below the formula maximum passes' );
$values['width'] = 450;
$check( $context->invoke( $engine, $values, $template )['width'] === 450.0, 'Explicit width takes precedence over the sidewise alias' );
$values['shelf.length'] = 350;
$check( true === $validate->invoke( $engine, $values, $template, $rule ), 'Standard width selections retain their existing formula behavior' );
$values = array( 'side_width' => 100, 'shelf.length' => 0 );
$check( true === $validate->invoke( $engine, $values, $template, $rule ), 'A zero formula maximum accepts zero' );
$builder = wcs_get_config_builder();
$customer = new ReflectionMethod( $builder, 'validate_customer_field_rows' );
$fields = array( array( 'key' => 'length', 'label' => 'Länge', 'type' => 'number', 'required' => false, 'min' => '', 'max' => '' ) );
$check( is_wp_error( $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => -5 ) ) ), 'Server rejects negative numbers when the minimum is unset' );
$check( true === $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 0 ) ), 'Server accepts zero without a configured minimum' );
$check( true === $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 5 ) ), 'Server accepts positive numbers without a configured minimum' );
$fields[0]['min'] = 150;
$check( is_wp_error( $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 149 ) ) ), 'Server preserves a configured positive minimum' );
$check( true === $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 150 ) ), 'Server accepts the configured minimum itself' );
$fields[0]['min'] = -20;
$check( is_wp_error( $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => -5 ) ) ), 'A negative configured minimum cannot bypass the server zero floor' );
$check( true === $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 0 ) ), 'A negative configured minimum still permits zero' );
$fields[0]['min'] = '';
$fields[0]['max'] = 300;
$check( is_wp_error( $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => -1 ) ) ) && is_wp_error( $customer->invoke( $builder, $fields, 'shelf', array( 'shelf.length' => 301 ) ) ), 'Maximum-only fields enforce both zero and the configured upper bound' );
echo "Completed $count range integration checks.\n";
