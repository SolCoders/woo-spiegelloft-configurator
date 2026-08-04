<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'suspension_plates',
	'label'          => __( 'Suspension Plates', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Aufhängung', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 10,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);