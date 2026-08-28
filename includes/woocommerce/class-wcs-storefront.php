<?php
/**
 * Storefront configurator UI.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_Storefront
 */
class WCS_Storefront {

	/**
	 * Config builder.
	 *
	 * @var WCS_Config_Builder
	 */
	private WCS_Config_Builder $config_builder;

	/**
	 * Whether the product summary has been replaced.
	 *
	 * @var bool
	 */
	private bool $summary_replaced = false;

	/**
	 * Whether the configurator has been injected into a block template.
	 *
	 * @var bool
	 */
	private bool $block_configurator_rendered = false;

	/**
	 * Whether this product render is using a block template path.
	 *
	 * @var bool
	 */
	private bool $block_template_active = false;

	/**
	 * Whether the configure entry button has already been rendered.
	 *
	 * @var bool
	 */
	private bool $configure_button_rendered = false;

	/**
	 * Whether the normal product summary layout has been adjusted.
	 *
	 * @var bool
	 */
	private bool $normal_summary_adjusted = false;

	/**
	 * Constructor.
	 *
	 * @param WCS_Config_Builder $config_builder Config builder.
	 */
	public function __construct( WCS_Config_Builder $config_builder ) {
		$this->config_builder = $config_builder;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'apply_product_layout_hooks' ) );
		add_action( 'woocommerce_before_single_product', array( $this, 'apply_product_layout_hooks' ), 1 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'apply_product_layout_hooks' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_configurator' ), 1 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_configure_button' ), 61 );
		add_filter( 'render_block', array( $this, 'filter_product_blocks' ), 10, 2 );
	}

	/**
	 * Remove default single-product elements for configured products.
	 */
	public function apply_product_layout_hooks(): void {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();
		if ( 'yes' !== get_post_meta( $product_id, '_wcs_configurator_enabled', true ) ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		if ( ! $this->is_configurator_view() ) {
			if ( ! $this->normal_summary_adjusted ) {
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
				add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 60 );
				$this->normal_summary_adjusted = true;
			}

			add_filter( 'body_class', array( $this, 'add_body_class' ) );
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

		if ( ! $this->summary_replaced ) {
			remove_all_actions( 'woocommerce_single_product_summary' );
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_configurator' ), 5 );
			$this->summary_replaced = true;
		}

		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Add a body class for configured product layout CSS.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_body_class( array $classes ): array {
		$classes[] = 'wcs-configurator-product';
		if ( $this->is_configurator_view() ) {
			$classes[] = 'wcs-configurator-view';
		}
		return $classes;
	}

	/**
	 * Replace Gutenberg/WooCommerce product summary blocks for configured products.
	 *
	 * @param string               $block_content Rendered block content.
	 * @param array<string, mixed> $block         Block data.
	 * @return string
	 */
	public function filter_product_blocks( string $block_content, array $block ): string {
		if ( ! $this->is_configured_product_page() || ! $this->is_main_product_block_context( $block ) ) {
			return $block_content;
		}

		$block_name = (string) ( $block['blockName'] ?? '' );
		if ( '' === $block_name ) {
			return $block_content;
		}

		if ( ! $this->is_configurator_view() ) {
			if ( in_array( $block_name, array( 'woocommerce/product-price' ), true ) ) {
				return '';
			}

			if ( in_array( $block_name, array( 'woocommerce/product-summary', 'core/post-excerpt' ), true ) ) {
				if ( $this->configure_button_rendered ) {
					return $block_content;
				}

				$this->configure_button_rendered = true;
				return $block_content . $this->get_product_price_html() . $this->get_configure_button_html();
			}

			if ( in_array( $block_name, array( 'woocommerce/add-to-cart-form', 'woocommerce/product-button' ), true ) ) {
				if ( $this->configure_button_rendered ) {
					return '';
				}

				$this->configure_button_rendered = true;
				return $this->get_product_price_html() . $this->get_configure_button_html();
			}

			return $block_content;
		}

		$suppress_blocks = array(
			'woocommerce/product-title',
			'woocommerce/product-price',
			'woocommerce/product-rating',
			'woocommerce/product-summary',
			'woocommerce/product-details',
			'woocommerce/add-to-cart-form',
			'woocommerce/product-button',
			'woocommerce/product-meta',
			'core/post-title',
			'core/post-excerpt',
		);

		$inject_blocks = array(
			'woocommerce/add-to-cart-form',
			'woocommerce/product-button',
			'woocommerce/product-summary',
			'woocommerce/product-details',
		);

		if ( ! in_array( $block_name, $suppress_blocks, true ) ) {
			return $block_content;
		}

		$this->block_template_active = true;

		if ( ! in_array( $block_name, $inject_blocks, true ) ) {
			return '';
		}

		if ( $this->block_configurator_rendered ) {
			return '';
		}

		$this->block_configurator_rendered = true;
		return $this->get_configurator_html();
	}

	/**
	 * Enqueue storefront assets.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_configured_product_page() ) {
			return;
		}

		wp_enqueue_style(
			'wcs-storefront',
			WCS_PLUGIN_URL . 'assets/css/storefront.css',
			array(),
			WCS_VERSION
		);

		wp_enqueue_script(
			'wcs-storefront',
			WCS_PLUGIN_URL . 'assets/js/storefront.js',
			array( 'jquery' ),
			WCS_VERSION,
			true
		);
	}

	/**
	 * Check whether the current request is an enabled configurator product page.
	 */
	private function is_configured_product_page(): bool {
		if ( ! is_product() ) {
			return false;
		}

		$product_id = get_queried_object_id();
		return $product_id > 0 && 'yes' === get_post_meta( $product_id, '_wcs_configurator_enabled', true );
	}

	/**
	 * Check whether a rendered block belongs to the main queried product.
	 *
	 * @param array<string, mixed> $block Block data.
	 */
	private function is_main_product_block_context( array $block ): bool {
		global $product;

		$queried_product_id = get_queried_object_id();
		if ( $queried_product_id <= 0 ) {
			return false;
		}

		$context = (array) ( $block['context'] ?? array() );
		$post_id = absint( $context['postId'] ?? $context['post_id'] ?? 0 );

		if ( $post_id > 0 ) {
			return $queried_product_id === $post_id;
		}

		if ( $product instanceof WC_Product ) {
			return $queried_product_id === $product->get_id();
		}

		return true;
	}

	/**
	 * Check whether the configurator view has been requested.
	 */
	private function is_configurator_view(): bool {
		return isset( $_GET['view'] ) && 'configurator' === sanitize_key( wp_unslash( $_GET['view'] ) );
	}

	/**
	 * Build configure button HTML.
	 */
	private function get_configure_button_html(): string {
		if ( ! $this->is_configured_product_page() || $this->is_configurator_view() ) {
			return '';
		}

		$url = add_query_arg( 'view', 'configurator', get_permalink( get_queried_object_id() ) );
		return '<div class="wcs-configure-entry"><a class="button wcs-configure-entry__button" href="' . esc_url( $url ) . '">' . esc_html__( 'Configure Now', 'woo-spiegelloft-configurator' ) . '</a></div>';
	}

	/**
	 * Build the normal product price HTML for block templates.
	 */
	private function get_product_price_html(): string {
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		return '<p class="price wcs-configure-entry-price">' . wp_kses_post( $product->get_price_html() ) . '</p>';
	}

	/**
	 * Build configurator HTML.
	 */
	private function get_configurator_html(): string {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( get_queried_object_id() );
		}

		if ( ! $product instanceof WC_Product || 'yes' !== get_post_meta( $product->get_id(), '_wcs_configurator_enabled', true ) ) {
			return '';
		}

		$config = $this->config_builder->build_for_product( $product->get_id() );
		if ( is_wp_error( $config ) ) {
			return '';
		}

		$images = (array) ( $config['images'] ?? array() );

		ob_start();
		include WCS_PLUGIN_DIR . 'templates/storefront/configurator.php';
		return (string) ob_get_clean();
	}

	/**
	 * Render configurator markup.
	 */
	public function render_configurator(): void {
		if ( ! $this->is_configurator_view() || $this->block_template_active || $this->block_configurator_rendered ) {
			return;
		}

		echo $this->get_configurator_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render the entry button on the normal product view.
	 */
	public function render_configure_button(): void {
		if ( $this->configure_button_rendered || ! is_product() || ! is_main_query() ) {
			return;
		}

		$html = $this->get_configure_button_html();
		if ( '' === $html ) {
			return;
		}

		$this->configure_button_rendered = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
