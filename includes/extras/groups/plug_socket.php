<?php
/**
 * Extra group: Plug Socket
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'slug'       => 'plug_socket',
	'label'      => __( 'Plug Socket', 'woo-spiegelloft-configurator' ),
	'input_type' => 'single',
	'position'   => 140,
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