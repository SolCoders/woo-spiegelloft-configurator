<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'makeup_mirror',
	'label'          => __( 'Make-up Mirror', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Schminkspiegel', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 110,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
	'optional_fields' => array(
		'mirror_type' => array(
			'type'  => 'repeater',
			'label' => __( 'Mirror type options', 'woo-spiegelloft-configurator' ),
			'fields' => array(
				array( 'id' => 'id', 'type' => 'text', 'label' => __( 'ID', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'name', 'type' => 'text', 'label' => __( 'Name', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'value', 'type' => 'text', 'label' => __( 'Value', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'image', 'type' => 'image', 'label' => __( 'Image', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'price', 'type' => 'price', 'label' => __( 'Price', 'woo-spiegelloft-configurator' ) ),
			),
		),
		'mirror_position' => array(
			'type'       => 'repeater',
			'label'      => __( 'Mirror position', 'woo-spiegelloft-configurator' ),
			'preset_key' => 'makeup_position',
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
		'mirror_light_color' => array(
			'type'  => 'repeater',
			'label' => __( 'Mirror light color', 'woo-spiegelloft-configurator' ),
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
		'separate_switch' => array(
			'type'  => 'repeater',
			'label' => __( 'Separate switch options', 'woo-spiegelloft-configurator' ),
			'fields' => array(
				array( 'id' => 'id', 'type' => 'text', 'label' => __( 'ID', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'name', 'type' => 'text', 'label' => __( 'Name', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'value', 'type' => 'text', 'label' => __( 'Value', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'image', 'type' => 'image', 'label' => __( 'Image', 'woo-spiegelloft-configurator' ) ),
				array( 'id' => 'price', 'type' => 'price', 'label' => __( 'Price', 'woo-spiegelloft-configurator' ) ),
			),
		),
		'position_switch' => array(
			'type'       => 'repeater',
			'label'      => __( 'Switch position', 'woo-spiegelloft-configurator' ),
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
		'side_distance' => array(
			'type'  => 'boolean',
			'label' => __( 'Side distance input', 'woo-spiegelloft-configurator' ),
		),
		'bottom_distance' => array(
			'type'  => 'boolean',
			'label' => __( 'Bottom distance input', 'woo-spiegelloft-configurator' ),
		),
	),
);