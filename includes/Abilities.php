<?php
/**
 * Read-only abilities for AI agents and other clients.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

use Catavio\Rest\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Describes catalog health to the WordPress Abilities API (WordPress 6.9+).
 *
 * An ability is a typed, permission-checked action that WordPress lists for
 * clients: the REST API and, through the MCP Adapter plugin, AI agents a store
 * owner connects to their own site. These three let such an agent answer
 * "which products need work?" from the last scan.
 *
 * All three are read-only. They never scan, never write and never contact
 * anything outside the site: each one runs the matching catavio/v1 REST route
 * internally, so permissions, validation and output stay identical to the
 * admin screens.
 *
 * On WordPress older than 6.9 nothing here runs.
 *
 * @since 1.1.0
 */
class Abilities {

	/**
	 * Ability category.
	 *
	 * @var string
	 */
	const CATEGORY = 'catavio';

	/**
	 * Register WordPress hooks when the Abilities API exists.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! self::is_supported() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Whether this WordPress has the Abilities API.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Catalog Health', 'catavio' ),
				'description' => __( 'Read how complete WooCommerce products are, from the last Catavio scan.', 'catavio' ),
			)
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		foreach ( $this->definitions() as $name => $definition ) {
			wp_register_ability( $name, $definition );
		}
	}

	/**
	 * Describe every ability.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions() {
		$shared = array(
			'category'            => self::CATEGORY,
			'permission_callback' => array( Capabilities::class, 'current_user_can_manage' ),
			'output_schema'       => array( 'type' => 'object' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'public'       => true,
				'mcp'          => array( 'public' => true ),
			),
		);

		return array(
			'catavio/get-catalog-health'      => array_merge(
				$shared,
				array(
					'label'            => __( 'Get catalog health', 'catavio' ),
					'description'      => __( 'Returns the summary of the last completed Catavio scan: average health score out of 100, how many products are healthy, need attention or are critical, the most common issues, and whether the figures are out of date.', 'catavio' ),
					'execute_callback' => array( $this, 'get_catalog_health' ),
				)
			),
			'catavio/list-products-by-health' => array_merge(
				$shared,
				array(
					'label'            => __( 'List products by health', 'catavio' ),
					'description'      => __( 'Returns one page of scanned products with their health score, health group and issue count. Filter by health group, issue identifier or product type; sort by score, title or last checked.', 'catavio' ),
					'input_schema'     => array(
						'type'                 => 'object',
						'default'              => array(),
						'additionalProperties' => false,
						'properties'           => array(
							'health'   => array(
								'type'        => 'string',
								'enum'        => array_merge( array( '' ), HealthScore::buckets() ),
								'description' => __( 'Health group: critical, attention or healthy. Empty for all.', 'catavio' ),
							),
							'issue'    => array(
								'type'        => 'string',
								'description' => __( 'Only products with this issue, for example missing_description.', 'catavio' ),
							),
							'type'     => array(
								'type'        => 'string',
								'description' => __( 'Product type, for example simple or variable.', 'catavio' ),
							),
							'search'   => array(
								'type'        => 'string',
								'description' => __( 'Search product names.', 'catavio' ),
							),
							'orderby'  => array(
								'type' => 'string',
								'enum' => array( 'score', 'title', 'checked' ),
							),
							'order'    => array(
								'type' => 'string',
								'enum' => array( 'asc', 'desc' ),
							),
							'page'     => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'per_page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 100,
							),
						),
					),
					'execute_callback' => array( $this, 'list_products' ),
				)
			),
			'catavio/get-product-health'      => array_merge(
				$shared,
				array(
					'label'            => __( 'Get product health', 'catavio' ),
					'description'      => __( 'Returns one product\'s health report from its last check: score, every issue with its severity and the points it cost, the checks it passed, and whether it was edited since.', 'catavio' ),
					'input_schema'     => array(
						'type'                 => 'object',
						'required'             => array( 'id' ),
						'additionalProperties' => false,
						'properties'           => array(
							'id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Product ID.', 'catavio' ),
							),
						),
					),
					'execute_callback' => array( $this, 'get_product_health' ),
				)
			),
		);
	}

	/**
	 * Execute catavio/get-catalog-health.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_catalog_health() {
		return $this->dispatch( '/summary' );
	}

	/**
	 * Execute catavio/list-products-by-health.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function list_products( $input = null ) {
		return $this->dispatch( '/products', is_array( $input ) ? $input : array() );
	}

	/**
	 * Execute catavio/get-product-health.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_product_health( $input = null ) {
		$id = is_array( $input ) && isset( $input['id'] ) ? absint( $input['id'] ) : 0;

		return $this->dispatch( '/products/' . $id );
	}

	/**
	 * Run a catavio/v1 GET route inside this request.
	 *
	 * @param string               $route  Route under the namespace.
	 * @param array<string, mixed> $params Query parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function dispatch( $route, array $params = array() ) {
		$request = new \WP_REST_Request( 'GET', '/' . Controller::NAMESPACE_V1 . $route );
		$request->set_query_params( $params );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		$data = $response->get_data();

		return is_array( $data ) ? $data : array();
	}
}
