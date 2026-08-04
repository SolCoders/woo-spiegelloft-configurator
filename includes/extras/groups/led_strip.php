<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'led_strip',
	'label'          => __( 'Double LED Strip', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'LED-Band doppelt', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 30,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);