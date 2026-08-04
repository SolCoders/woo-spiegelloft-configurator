<?php
/**
 * Plugin loader: autoloader and asset registration.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Loader
 */
class WCS_Loader {

	/**
	 * PSR-4-like class map for WCS_ prefixed classes.
	 *
	 * @var array<string, string>
	 */
	private array $class_map = array(
		'WCS_I18n'                    => 'includes/class-wcs-i18n.php',
		'WCS_Plugin'                    => 'includes/class-wcs-plugin.php',
		'WCS_Activator'                 => 'includes/class-wcs-activator.php',
		'WCS_Deactivator'               => 'includes/class-wcs-deactivator.php',
		'WCS_Cache'                     => 'includes/class-wcs-cache.php',
		'WCS_Extra_Field_Type_Registry' => 'includes/extras/class-wcs-extra-field-type-registry.php',
		'WCS_Extras_Registry'           => 'includes/class-wcs-extras-registry.php',
		'WCS_Extras_Catalog'            => 'includes/class-wcs-extras-catalog.php',
		'WCS_Template'                  => 'includes/class-wcs-template.php',
		'WCS_Validation_Engine'         => 'includes/class-wcs-validation-engine.php',
		'WCS_Config_Builder'            => 'includes/class-wcs-config-builder.php',
		'WCS_Admin'                     => 'includes/admin/class-wcs-admin.php',
		'WCS_Extras_Admin'              => 'includes/admin/class-wcs-extras-admin.php',
		'WCS_Template_Admin'            => 'includes/admin/class-wcs-template-admin.php',
		'WCS_Product_Meta'              => 'includes/admin/class-wcs-product-meta.php',
		'WCS_REST_Controller'           => 'includes/api/class-wcs-rest-controller.php',
		'WCS_Cart'                      => 'includes/woocommerce/class-wcs-cart.php',
		'WCS_Order'                     => 'includes/woocommerce/class-wcs-order.php',
	);

	/**
	 * Field type class map.
	 *
	 * @var array<string, string>
	 */
	private array $field_type_map = array(
		'WCS_Extra_Field_Type_Text'           => 'includes/extras/fields/class-wcs-extra-field-type-text.php',
		'WCS_Extra_Field_Type_Price'          => 'includes/extras/fields/class-wcs-extra-field-type-price.php',
		'WCS_Extra_Field_Type_Image'          => 'includes/extras/fields/class-wcs-extra-field-type-image.php',
		'WCS_Extra_Field_Type_Boolean'        => 'includes/extras/fields/class-wcs-extra-field-type-boolean.php',
		'WCS_Extra_Field_Type_Repeater'       => 'includes/extras/fields/class-wcs-extra-field-type-repeater.php',
		'WCS_Extra_Field_Type_Nested_Options' => 'includes/extras/fields/class-wcs-extra-field-type-nested-options.php',
	);

	/**
	 * Bootstrap the plugin.
	 */
	public function run(): void {
		spl_autoload_register( array( $this, 'autoload' ) );

		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Autoload WCS classes.
	 *
	 * @param string $class Class name.
	 */
	public function autoload( string $class ): void {
		if ( 0 !== strpos( $class, 'WCS_' ) ) {
			return;
		}

		$maps = array( $this->class_map, $this->field_type_map );

		foreach ( $maps as $map ) {
			if ( isset( $map[ $class ] ) ) {
				$file = WCS_PLUGIN_DIR . $map[ $class ];
				if ( file_exists( $file ) ) {
					require_once $file;
				}
				return;
			}
		}
	}

	/**
	 * Initialize plugin after WooCommerce is loaded.
	 */
	public function init_plugin(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		new WCS_I18n();
		$plugin = new WCS_Plugin();
		$plugin->run();
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Woo Spiegelloft Configurator requires WooCommerce to be installed and active.', 'woo-spiegelloft-configurator' );
		echo '</p></div>';
	}

	/**
	 * Enqueue admin CSS/JS on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$plugin_screens = array(
			'toplevel_page_wcs-configurator',
			'wcs-configurator_page_wcs-extras',
			'wcs_extra_option',
			'wcs_template',
			'product',
		);

		$is_plugin_screen = in_array( $screen->id, $plugin_screens, true )
			|| in_array( $screen->post_type ?? '', array( 'wcs_extra_option', 'wcs_template' ), true );

		if ( ! $is_plugin_screen ) {
			return;
		}

		wp_enqueue_style(
			'wcs-admin',
			WCS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WCS_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'wcs-admin-extras',
			WCS_PLUGIN_URL . 'assets/js/admin-extras.js',
			array( 'jquery', 'wp-util' ),
			WCS_VERSION,
			true
		);

		wp_enqueue_script(
			'wcs-admin-template',
			WCS_PLUGIN_URL . 'assets/js/admin-template.js',
			array( 'jquery' ),
			WCS_VERSION,
			true
		);

		wp_enqueue_script(
			'wcs-admin-validation',
			WCS_PLUGIN_URL . 'assets/js/admin-validation.js',
			array( 'jquery' ),
			WCS_VERSION,
			true
		);

		wp_localize_script(
			'wcs-admin-extras',
			'wcsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wcs_admin_nonce' ),
				'i18n'    => array(
					'saved'   => __( 'Saved.', 'woo-spiegelloft-configurator' ),
					'error'   => __( 'An error occurred.', 'woo-spiegelloft-configurator' ),
					'confirm' => __( 'Are you sure?', 'woo-spiegelloft-configurator' ),
				),
			)
		);
	}
}