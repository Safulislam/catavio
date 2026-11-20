<?php
/**
 * The REST API, through the real server and permission checks.
 *
 * @package Catavio
 */

namespace Catavio\Tests\Integration;

use WP_REST_Request;

/**
 * Who may read and act, and what "Check again" changes.
 */
class RestTest extends TestCase {

	/**
	 * Request a route as the current user.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route under /catavio/v1.
	 * @param array  $params Body parameters.
	 * @return \WP_REST_Response
	 */
	private function request( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/catavio/v1' . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Every route, with a request that would succeed for a permitted user.
	 *
	 * @param int $product_id A product to use.
	 * @return array[]
	 */
	private function routes( $product_id ) {
		return array(
			array( 'GET', '/summary', array() ),
			array( 'GET', '/products', array() ),
			array( 'GET', "/products/{$product_id}", array() ),
			array( 'POST', "/products/{$product_id}/scan", array() ),
			array( 'GET', '/scan', array() ),
			array( 'POST', '/scan', array() ),
			array( 'POST', '/scan/batch', array() ),
			array( 'DELETE', '/scan', array() ),
			array( 'GET', '/settings', array() ),
			array( 'POST', '/settings', array( 'disabledChecks' => array() ) ),
		);
	}

	/**
	 * Logged out visitors are refused everywhere.
	 *
	 * @return void
	 */
	public function test_logged_out_visitors_are_refused_everywhere() {
		$product = $this->complete_simple();
		wp_set_current_user( 0 );

		foreach ( $this->routes( $product->get_id() ) as $route ) {
			$this->assertSame( 401, $this->request( ...$route )->get_status(), implode( ' ', array( $route[0], $route[1] ) ) );
		}
	}

	/**
	 * Customers and subscribers are refused everywhere.
	 *
	 * @return void
	 */
	public function test_customers_and_subscribers_are_refused_everywhere() {
		$product = $this->complete_simple();

		foreach ( array( 'customer', 'subscriber' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			foreach ( $this->routes( $product->get_id() ) as $route ) {
				$this->assertSame( 403, $this->request( ...$route )->get_status(), "{$role}: {$route[0]} {$route[1]}" );
			}
		}
	}

	/**
	 * Shop managers and administrators are allowed.
	 *
	 * @return void
	 */
	public function test_shop_managers_and_administrators_are_allowed() {
		$product = $this->complete_simple();

		foreach ( array( 'shop_manager', 'administrator' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			foreach ( $this->routes( $product->get_id() ) as $route ) {
				$status = $this->request( ...$route )->get_status();

				$this->assertLessThan( 300, $status, "{$role}: {$route[0]} {$route[1]} returned {$status}" );
			}
		}
	}

	/**
	 * Check again updates the product and marks the dashboard.
	 *
	 * @return void
	 */
	public function test_check_again_updates_the_product_and_marks_the_dashboard() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$product = $this->complete_simple( array( 'description' => '' ) );
		$this->run_full_scan();

		$this->assertContains( 'missing_description', $this->issue_codes( $product->get_id() ) );
		$this->assertFalse( $this->request( 'GET', '/summary' )->get_data()['rechecked'] );

		$product->set_description( 'Now with a description.' );
		$product->save();

		$response = $this->request( 'POST', '/products/' . $product->get_id() . '/scan' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 100, $response->get_data()['score'] );
		$this->assertSame( array(), $this->issue_codes( $product->get_id() ) );
		$this->assertSame( 100, (int) get_post_meta( $product->get_id(), '_catavio_score', true ) );
		$this->assertTrue( $this->request( 'GET', '/summary' )->get_data()['rechecked'] );
	}

	/**
	 * Check again on a missing product is a 404.
	 *
	 * @return void
	 */
	public function test_check_again_on_a_missing_product_is_a_404() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 404, $this->request( 'POST', '/products/999999/scan' )->get_status() );
	}
}
