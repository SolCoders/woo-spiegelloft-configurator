<?php
/**
 * Version-based object cache and transients.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Cache
 */
class WCS_Cache {

	public const GROUP = 'wcs_configurator';

	/**
	 * Get current cache version.
	 */
	public function get_version(): string {
		return (string) get_option( 'wcs_cache_version', '1' );
	}

	/**
	 * Bump cache version to invalidate cached data.
	 */
	public function bump_version(): void {
		update_option( 'wcs_cache_version', (string) time() );
	}

	/**
	 * Build a versioned cache key.
	 *
	 * @param string $key Base key.
	 */
	public function key( string $key ): string {
		return 'wcs_' . $this->get_version() . '_' . $key;
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, $default = false ) {
		$full_key = $this->key( $key );
		$cached   = wp_cache_get( $full_key, self::GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$transient = get_transient( $full_key );
		if ( false !== $transient ) {
			wp_cache_set( $full_key, $transient, self::GROUP, HOUR_IN_SECONDS );
			return $transient;
		}

		return $default;
	}

	/**
	 * Set cached value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   TTL in seconds.
	 */
	public function set( string $key, $value, int $ttl = HOUR_IN_SECONDS ): void {
		$full_key = $this->key( $key );
		wp_cache_set( $full_key, $value, self::GROUP, $ttl );
		set_transient( $full_key, $value, $ttl );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): void {
		$full_key = $this->key( $key );
		wp_cache_delete( $full_key, self::GROUP );
		delete_transient( $full_key );
	}

	/**
	 * Flush all WCS transients and bump version.
	 */
	public static function flush_all(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_wcs_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wcs_' ) . '%'
			)
		);

		update_option( 'wcs_cache_version', (string) time() );
	}
}