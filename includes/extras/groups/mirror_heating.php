<?php
/**
 * Extra group: Mirror Heating
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'slug'       => 'mirror_heating',
	'label'      => __( 'Mirror Heating', 'woo-spiegelloft-configurator' ),
	'input_type' => 'single',
	'position'   => 90,
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