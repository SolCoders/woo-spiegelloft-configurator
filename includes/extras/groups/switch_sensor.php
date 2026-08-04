<?php
/**
 * Extra group: Switch / Sensor
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'slug'       => 'switch_sensor',
	'label'      => __( 'Switch / Sensor', 'woo-spiegelloft-configurator' ),
	'input_type' => 'single',
	'position'   => 40,
	'required'   => false,
	'fields'     => array(
		array(
			'id'    => 'title',
			'type'  => 'text',
			'label' => __( 'Title', 'woo-spiegelloft-configurator' ),
		),
		array(
			'id'    => 'price',
			'type'  => 'price',
			'label' => __( 'Price', 'woo-spiegelloft-configurator' ),
		),
		array(
			'id'    => 'image',
			'type'  => 'image',
			'label' => __( 'Image', 'woo-spiegelloft-configurator' ),
		),
	),
);