<?php
/**
 * Admin navigation: one menu entry, two views, one tab argument.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Admin\Menu;
use Catavio\Admin\Screen;
use Catavio\Capabilities;

/**
 * Covers the shape of the admin navigation.
 *
 * The sidebar used to offer two routes to the same screen, because WordPress
 * repeats a top level menu item as its own first submenu entry as soon as any
 * submenu exists. These tests are what keeps that from coming back: registering
 * a single submenu would restore the duplicate, and nothing else in the suite
 * would notice.
 */
class AdminNavigationTest extends TestCase {

	/**
	 * Reset the recorded registrations and the request.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_menu_pages']    = array();
		$GLOBALS['catavio_test_submenu_pages'] = array();
		$GLOBALS['catavio_test_roles']         = array( 'administrator' => array( 'manage_woocommerce' ) );

		unset( $_GET[ Screen::TAB ] );
		Capabilities::reset();
	}

	/**
	 * Leave the request as it was found.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_GET[ Screen::TAB ] );
		Capabilities::reset();

		parent::tearDown();
	}

	/**
	 * Exactly one sidebar entry, and no submenu to duplicate it.
	 *
	 * @return void
	 */
	public function test_registers_one_menu_entry_and_no_submenu() {
		( new Menu() )->add_menu_pages();

		$this->assertCount( 1, $GLOBALS['catavio_test_menu_pages'], 'The plugin must register exactly one top level menu entry.' );
		$this->assertCount(
			0,
			$GLOBALS['catavio_test_submenu_pages'],
			'Registering any submenu makes WordPress repeat the top level entry, which is the duplicate this structure exists to avoid.'
		);
		$this->assertSame( Screen::PAGE, $GLOBALS['catavio_test_menu_pages'][0]['menu_slug'] );
	}

	/**
	 * The menu entry is guarded by the plugin's own capability.
	 *
	 * @return void
	 */
	public function test_menu_entry_requires_the_plugin_capability() {
		( new Menu() )->add_menu_pages();

		$this->assertSame( Capabilities::get(), $GLOBALS['catavio_test_menu_pages'][0]['capability'] );
	}

	/**
	 * The dashboard is the bare page URL; only other views carry a tab.
	 *
	 * @return void
	 */
	public function test_dashboard_url_carries_no_tab_argument() {
		$url = Screen::url( Screen::VIEW_DASHBOARD );

		$this->assertStringContainsString( 'page=' . Screen::PAGE, $url );
		$this->assertStringNotContainsString( Screen::TAB . '=', $url );
	}

	/**
	 * Every other view is addressed by the tab argument on the same page.
	 *
	 * @return void
	 */
	public function test_product_view_url_carries_the_tab_argument() {
		$url = Screen::url( Screen::VIEW_PRODUCTS );

		$this->assertStringContainsString( 'page=' . Screen::PAGE, $url );
		$this->assertStringContainsString( Screen::TAB . '=' . Screen::VIEW_PRODUCTS, $url );
	}

	/**
	 * A request with no tab shows the dashboard.
	 *
	 * @return void
	 */
	public function test_view_defaults_to_the_dashboard() {
		$this->assertSame( Screen::VIEW_DASHBOARD, Screen::current_view() );
	}

	/**
	 * A recognised tab selects its view.
	 *
	 * @return void
	 */
	public function test_known_tab_selects_its_view() {
		$_GET[ Screen::TAB ] = Screen::VIEW_PRODUCTS;

		$this->assertSame( Screen::VIEW_PRODUCTS, Screen::current_view() );
	}

	/**
	 * Anything unrecognised falls back rather than rendering nothing.
	 *
	 * @param string $tab Value supplied in the request.
	 * @return void
	 *
	 * @dataProvider provide_unusable_tabs
	 */
	public function test_unknown_tab_falls_back_to_the_dashboard( $tab ) {
		$_GET[ Screen::TAB ] = $tab;

		$this->assertSame( Screen::VIEW_DASHBOARD, Screen::current_view() );
	}

	/**
	 * Tab values a hand-edited or stale URL might carry.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_unusable_tabs() {
		return array(
			'empty'             => array( '' ),
			'unknown view'      => array( 'settings' ),
			'retired page slug' => array( 'catavio-products' ),
			'markup'            => array( '<script>' ),
		);
	}
}
