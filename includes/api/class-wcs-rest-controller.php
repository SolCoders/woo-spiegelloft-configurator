<?php
/**
 * REST API controller.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Class WCS_REST_Controller
 */
class WCS_REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wcs/v1';

	/**
	 * Config builder.
	 *
	 * @var WCS_Config_Builder
	 */
	private WCS_Config_Builder $config_builder;

	/**
	 * Validation engine.
	 *
	 * @var WCS_Validation_Engine
	 */
	private WCS_Validation_Engine $validation;

	/**
	 * Constructor.
	 *
	 * @param WCS_Config_Builder    $config_builder Config builder.
	 * @param WCS_Validation_Engine $validation     Validation engine.
	 */
	public function __construct( WCS_Config_Builder $config_builder, WCS_Validation_Engine $validation ) {
		$this->config_builder = $config_builder;
		$this->validation     = $validation;
	}

	/**
	 * Register REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/config/(?P<product_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'product_id' => array(
							'required'          => true,
							'validate_callback' => static function ( $param ): bool {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/validate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'validate_config' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/price',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'calculate_price' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * GET /wcs/v1/config/{product_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_config( WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'product_id' );
		$config     = $this->config_builder->build_for_product( $product_id );

		if ( is_wp_error( $config ) ) {
			return $config;
		}

		return rest_ensure_response( $config );
	}

	/**
	 * POST /wcs/v1/validate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_config( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$selections = (array) $request->get_param( 'selections' );

		$result = $this->config_builder->build_cart_configuration( $product_id, $selections );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'valid' => true, 'data' => $result ) );
	}

	/**
	 * POST /wcs/v1/price
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function calculate_price( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$selections = $this->validation->sanitize_selections( (array) $request->get_param( 'selections' ) );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'wcs_invalid_product', __( 'Invalid product.', 'woo-spiegelloft-configurator' ), array( 'status' => 404 ) );
		}

		$price = $this->config_builder->calculate_price( (float) $product->get_price(), $selections );

		return rest_ensure_response(
			array(
				'product_id' => $product_id,
				'price'      => $price,
				'formatted'  => wc_price( $price ),
			)
		);
	}
}