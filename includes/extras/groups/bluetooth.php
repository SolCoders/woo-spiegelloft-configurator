<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'bluetooth',
	'label'          => __( 'Bluetooth Speaker', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Bluetooth Lautsprecher', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 130,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);