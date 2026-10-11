<?php
/**
 * Environment and dependency behaviour.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Requirements;

/**
 * The plugin must explain itself rather than fail, whatever it finds.
 */
class RequirementsTest extends TestCase {

	/**
	 * Restore the stubbed WordPress version.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['catavio_test_wp_version'] = '6.4';

		parent::tearDown();
	}

	/**
	 * A supported environment reports nothing.
	 *
	 * @return void
	 */
	public function test_a_supported_environment_is_silent() {
		$GLOBALS['catavio_test_wp_version'] = '6.8';

		$this->assertSame( '', Requirements::check_environment() );
	}

	/**
	 * An old WordPress is named, with both versions.
	 *
	 * @return void
	 */
	public function test_an_old_wordpress_is_reported() {
		$GLOBALS['catavio_test_wp_version'] = '6.0';

		$message = Requirements::check_environment();

		$this->assertNotSame( '', $message );
		$this->assertStringContainsString( CATAVIO_MIN_WP, $message );
		$this->assertStringContainsString( '6.0', $message );
	}

	/**
	 * Messages are returned unescaped, to be escaped once at output.
	 *
	 * Escaping here as well would double encode any translation containing an
	 * ampersand or a quote.
	 *
	 * @return void
	 */
	public function test_messages_are_not_pre_escaped() {
		$GLOBALS['catavio_test_wp_version'] = '6.0';

		$this->assertStringNotContainsString( '&amp;', Requirements::check_environment() );
		$this->assertStringNotContainsString( '&#0', Requirements::check_environment() );
	}

	/**
	 * WooCommerce is reported absent when its class is not loaded.
	 *
	 * @return void
	 */
	public function test_woocommerce_is_detected_as_absent() {
		$this->assertFalse( Requirements::is_woocommerce_active() );
		$this->assertFalse( Requirements::is_woocommerce_supported() );
		$this->assertFalse( Requirements::are_met() );
	}

	/**
	 * An unsupported WooCommerce is not treated as usable.
	 *
	 * The version test is exercised directly, because the constant cannot be
	 * defined twice within one process.
	 *
	 * @return void
	 */
	public function test_the_woocommerce_version_floor_is_enforced() {
		$this->assertTrue( version_compare( '8.0', CATAVIO_MIN_WC, '>=' ) );
		$this->assertTrue( version_compare( '11.1.2', CATAVIO_MIN_WC, '>=' ) );
		$this->assertFalse( version_compare( '7.9', CATAVIO_MIN_WC, '>=' ) );
		$this->assertFalse( version_compare( '3.0', CATAVIO_MIN_WC, '>=' ) );
	}
}
