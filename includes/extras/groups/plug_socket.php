<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'plug_socket',
	'label'          => __( 'Plug for Socket', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Stecker für Steckdose', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 140,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);