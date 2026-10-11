<?php
/**
 * Capability resolution.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Capabilities;

/**
 * The single gate every screen and every REST route depends on.
 */
class CapabilitiesTest extends TestCase {

	/**
	 * Reset the stubbed roles and memoized capability.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_roles']     = array();
		$GLOBALS['catavio_test_user_caps'] = array();

		Capabilities::reset();
	}

	/**
	 * Clean up so one test cannot leak into the next.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Capabilities::reset();

		parent::tearDown();
	}

	/**
	 * WooCommerce's capability is preferred when it exists.
	 *
	 * @return void
	 */
	public function test_prefers_the_woocommerce_capability() {
		$GLOBALS['catavio_test_roles'] = array(
			'administrator' => array( 'manage_options', 'manage_woocommerce', 'edit_products' ),
			'shop_manager'  => array( 'manage_woocommerce', 'edit_products' ),
		);

		$this->assertSame( Capabilities::PRIMARY, Capabilities::get() );
	}

	/**
	 * Falls back to editing products when WooCommerce caps are absent.
	 *
	 * @return void
	 */
	public function test_falls_back_to_editing_products() {
		$GLOBALS['catavio_test_roles'] = array(
			'administrator' => array( 'manage_options', 'edit_products' ),
		);

		$this->assertSame( Capabilities::FALLBACK, Capabilities::get() );
	}

	/**
	 * Falls back to a core capability when neither is registered.
	 *
	 * This is the state a site is in while WooCommerce is deactivated.
	 *
	 * @return void
	 */
	public function test_falls_back_to_a_core_capability() {
		$GLOBALS['catavio_test_roles'] = array(
			'administrator' => array( 'manage_options' ),
			'subscriber'    => array( 'read' ),
		);

		$this->assertSame( Capabilities::CORE_FALLBACK, Capabilities::get() );
	}

	/**
	 * A site with no roles at all still resolves to something safe.
	 *
	 * @return void
	 */
	public function test_resolves_safely_with_no_roles() {
		$this->assertSame( Capabilities::CORE_FALLBACK, Capabilities::get() );
	}

	/**
	 * The resolved capability is memoized.
	 *
	 * @return void
	 */
	public function test_the_resolved_capability_is_memoized() {
		$GLOBALS['catavio_test_roles'] = array( 'administrator' => array( 'manage_woocommerce' ) );

		$this->assertSame( Capabilities::PRIMARY, Capabilities::get() );

		// Roles change, but nothing has asked for a re-resolve.
		$GLOBALS['catavio_test_roles'] = array( 'administrator' => array( 'manage_options' ) );

		$this->assertSame( Capabilities::PRIMARY, Capabilities::get() );

		Capabilities::reset();

		$this->assertSame( Capabilities::CORE_FALLBACK, Capabilities::get() );
	}

	/**
	 * A user is allowed only when they hold the resolved capability.
	 *
	 * @return void
	 */
	public function test_only_a_user_holding_the_capability_is_allowed() {
		$GLOBALS['catavio_test_roles'] = array( 'administrator' => array( 'manage_woocommerce' ) );

		$GLOBALS['catavio_test_user_caps'] = array();
		$this->assertFalse( Capabilities::current_user_can_manage() );

		$GLOBALS['catavio_test_user_caps'] = array( 'read', 'edit_posts' );
		$this->assertFalse( Capabilities::current_user_can_manage() );

		$GLOBALS['catavio_test_user_caps'] = array( 'manage_woocommerce' );
		$this->assertTrue( Capabilities::current_user_can_manage() );
	}
}
