<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'glass_shelf',
	'label'          => __( 'Glass Shelf', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Ablage aus Glas', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 70,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);