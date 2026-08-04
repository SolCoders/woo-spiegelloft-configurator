<?php
/**
 * Extra group: Suspension Plates
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'slug'       => 'suspension_plates',
	'label'      => __( 'Suspension Plates', 'woo-spiegelloft-configurator' ),
	'input_type' => 'single',
	'position'   => 10,
	'required'   => true,
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