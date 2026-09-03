<?php
/**
 * Plugin Name:       Woo Spiegelloft Configurator
 * Plugin URI:        https://github.com/SolCoders/woo-spiegelloft-configurator
 * Description:       Dynamic mirror configurator for WooCommerce — templates, extras catalog, and REST API.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SolCoders
 * Text Domain:       woo-spiegelloft-configurator
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 *
 * @package WooSpiegelloftConfigurator
 */

defined( 'ABSPATH' ) || exit;

define( 'WCS_VERSION', '1.0.1' );
define( 'WCS_PLUGIN_FILE', __FILE__ );
define( 'WCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WCS_PLUGIN_DIR . 'includes/class-wcs-loader.php';

register_activation_hook( __FILE__, array( 'WCS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCS_Deactivator', 'deactivate' ) );

$wcs_loader = new WCS_Loader();
$wcs_loader->run();
