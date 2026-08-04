<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'sockets',
	'label'          => __( 'Sockets', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Steckdose(n) inkl. Bohrung', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 100,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
	'optional_fields' => array(
		'socket_position' => array(
			'type'       => 'repeater',
			'label'      => __( 'Socket position', 'woo-spiegelloft-configurator' ),
			'preset_key' => 'socket_single',
			'fields' => array(
				array(
					'id'    => 'title',
					'type'  => 'text',
					'label' => __( 'Title', 'woo-spiegelloft-configurator' ),
				),
				array(
					'id'    => 'image',
					'type'  => 'image',
					'label' => __( 'Image', 'woo-spiegelloft-configurator' ),
				),
			),
		),
		'socket_colors' => array(
			'type'  => 'repeater',
			'label' => __( 'Socket colors', 'woo-spiegelloft-configurator' ),
			'fields' => array(
				array(
					'id'    => 'title',
					'type'  => 'text',
					'label' => __( 'Title', 'woo-spiegelloft-configurator' ),
				),
				array(
					'id'    => 'image',
					'type'  => 'image',
					'label' => __( 'Image', 'woo-spiegelloft-configurator' ),
				),
			),
		),
	),
);