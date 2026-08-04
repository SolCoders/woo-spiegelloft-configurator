<?php
/**
 * Uninstall handler.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wcs_seed_version' );
delete_option( 'wcs_cache_version' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wcs_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wcs_' ) . '%'
	)
);