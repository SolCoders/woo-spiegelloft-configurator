<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'edge',
	'label'          => __( 'Edge', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Kanten', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 150,
	'type'           => 'static',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);