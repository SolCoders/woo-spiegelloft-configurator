<?php
/**
 * Internationalization.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_I18n
 */
class WCS_I18n {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'woo-spiegelloft-configurator',
			false,
			dirname( WCS_PLUGIN_BASENAME ) . '/languages'
		);
	}
}