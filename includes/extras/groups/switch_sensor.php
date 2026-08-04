<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'switch_sensor',
	'label'          => __( 'Switch / Sensor', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Schalter/Sensor', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 40,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
	'optional_fields' => array(
		'position' => array(
			'type'       => 'repeater',
			'label'      => __( 'Where can the customer place this?', 'woo-spiegelloft-configurator' ),
			'preset_key' => 'bottom_row',
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