<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'mirror_heating',
	'label'          => __( 'Mirror Heating', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Spiegelheizung', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 90,
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