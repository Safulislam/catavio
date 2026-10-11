<?php
/**
 * Shared REST controller behaviour.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Rest;

use Catavio\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for this plugin's REST controllers.
 *
 * Exists so the namespace and the permission rule are declared once. A
 * controller that forgets its permission_callback is a security hole, so no
 * subclass is allowed to define its own.
 */
abstract class Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE_V1 = 'catavio/v1';

	/**
	 * Register this controller's routes.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Permission callback shared by every route.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission() {
		if ( Capabilities::current_user_can_manage() ) {
			return true;
		}

		return new \WP_Error(
			'catavio_forbidden',
			__( 'You do not have permission to manage product health.', 'catavio' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Fail when WooCommerce is unavailable.
	 *
	 * A second line of defence, not the first. Plugin::boot() only hooks
	 * rest_api_init once WooCommerce has been confirmed present and supported,
	 * so with WooCommerce inactive these routes are never registered and a
	 * request for one returns rest_no_route. Verified: with WooCommerce
	 * deactivated, every route in this namespace answers 404.
	 *
	 * It is kept so that a future change to where routes are registered cannot
	 * silently expose a WooCommerce-dependent route without one.
	 *
	 * @return \WP_Error|null
	 */
	protected function require_woocommerce() {
		if ( function_exists( 'wc_get_product' ) ) {
			return null;
		}

		return new \WP_Error(
			'catavio_woocommerce_inactive',
			__( 'WooCommerce is not active.', 'catavio' ),
			array( 'status' => 409 )
		);
	}
}
