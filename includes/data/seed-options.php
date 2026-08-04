<?php
/**
 * Seed data for default extra options.
 *
 * @package WooSpiegelloftConfigurator
 *
 * Structure: group_slug => array of options with title, slug, meta.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'suspension_plates' => array(
		array(
			'title' => 'Standard suspension',
			'slug'  => 'standard',
			'meta'  => array( '_wcs_price' => '0.00', '_wcs_image' => '' ),
		),
		array(
			'title' => 'Reinforced suspension',
			'slug'  => 'reinforced',
			'meta'  => array( '_wcs_price' => '25.00', '_wcs_image' => '' ),
		),
	),
	'light_color' => array(
		array(
			'title' => 'Warm white 3000K',
			'slug'  => 'warm-white-3000k',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Neutral white 4000K',
			'slug'  => 'neutral-white-4000k',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Daylight 6500K',
			'slug'  => 'daylight-6500k',
			'meta'  => array( '_wcs_price' => '15.00' ),
		),
	),
	'led_strip' => array(
		array(
			'title' => 'No LED',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Perimeter LED',
			'slug'  => 'perimeter',
			'meta'  => array( '_wcs_price' => '89.00' ),
		),
	),
	'switch_sensor' => array(
		array(
			'title' => 'Standard switch',
			'slug'  => 'standard-switch',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Touch sensor',
			'slug'  => 'touch-sensor',
			'meta'  => array( '_wcs_price' => '45.00' ),
		),
	),
	'side_panels' => array(
		array(
			'title' => 'No side panels',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Left panel',
			'slug'  => 'left',
			'meta'  => array( '_wcs_price' => '35.00' ),
		),
		array(
			'title' => 'Right panel',
			'slug'  => 'right',
			'meta'  => array( '_wcs_price' => '35.00' ),
		),
	),
	'material' => array(
		array(
			'title' => 'Standard mirror glass',
			'slug'  => 'standard',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Premium anti-fog glass',
			'slug'  => 'premium',
			'meta'  => array( '_wcs_price' => '75.00' ),
		),
	),
	'glass_shelf' => array(
		array(
			'title' => 'No shelf',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Single glass shelf',
			'slug'  => 'single',
			'meta'  => array( '_wcs_price' => '29.00' ),
		),
	),
	'clock' => array(
		array(
			'title' => 'No clock',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Integrated LED clock',
			'slug'  => 'led-clock',
			'meta'  => array( '_wcs_price' => '39.00' ),
		),
	),
	'mirror_heating' => array(
		array(
			'title' => 'No heating',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Demister pad',
			'slug'  => 'demister',
			'meta'  => array( '_wcs_price' => '55.00' ),
		),
	),
	'sockets' => array(
		array(
			'title' => 'No socket',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Shaver socket',
			'slug'  => 'shaver',
			'meta'  => array( '_wcs_price' => '49.00' ),
		),
	),
	'makeup_mirror' => array(
		array(
			'title' => 'No make-up mirror',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => '3x magnifying mirror',
			'slug'  => '3x',
			'meta'  => array( '_wcs_price' => '65.00' ),
		),
	),
	'sealing' => array(
		array(
			'title' => 'Standard sealing',
			'slug'  => 'standard',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'IP44 bathroom sealing',
			'slug'  => 'ip44',
			'meta'  => array( '_wcs_price' => '20.00' ),
		),
	),
	'bluetooth' => array(
		array(
			'title' => 'No Bluetooth',
			'slug'  => 'none',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Bluetooth speaker',
			'slug'  => 'speaker',
			'meta'  => array( '_wcs_price' => '79.00' ),
		),
	),
	'plug_socket' => array(
		array(
			'title' => 'Hardwired',
			'slug'  => 'hardwired',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Plug socket',
			'slug'  => 'plug',
			'meta'  => array( '_wcs_price' => '15.00' ),
		),
	),
	'edge' => array(
		array(
			'title' => 'Polished edge',
			'slug'  => 'polished',
			'meta'  => array( '_wcs_price' => '0.00' ),
		),
		array(
			'title' => 'Bevelled edge',
			'slug'  => 'bevelled',
			'meta'  => array( '_wcs_price' => '30.00' ),
		),
	),
);