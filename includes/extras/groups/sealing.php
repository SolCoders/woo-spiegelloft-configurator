<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'sealing',
	'label'          => __( 'Sealing', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Versiegelung', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 120,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);