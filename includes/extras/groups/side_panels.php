<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

return array(
	'slug'           => 'side_panels',
	'label'          => __( 'Side Panels', 'woo-spiegelloft-configurator' ),
	'category_title' => __( 'Seiten- / Vollverblendung', 'woo-spiegelloft-configurator' ),
	'input_type'     => 'single',
	'position'       => 50,
	'type'           => 'selectable',
	'base_fields'    => array( 'name', 'value', 'image', 'price' ),
);