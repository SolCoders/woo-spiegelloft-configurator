<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'light_color',
	'label'          => __( 'Light Color', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Lichtfarbe', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 20,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
	'optional_fields' => array(
		'placement' => array(
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