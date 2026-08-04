<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'material',
	'label'          => __( 'Panel Material', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Blenden Material', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 60,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);