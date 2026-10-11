<?php
/**
 * Admin menu registration.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Admin;

use Catavio\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's admin menu.
 *
 * One top level item and no submenus. WordPress repeats a top level item as
 * its own first submenu entry the moment any submenu exists, which would put
 * two routes to the same page in the sidebar; the views are told apart by the
 * tabs on the page instead.
 *
 * The hook suffix returned by add_menu_page() is the only reliable way to
 * identify our page later. Guessing it from the slug breaks as soon as a menu
 * title changes, so the value is captured here and read back by the asset
 * loader.
 */
class Menu {

	/**
	 * Menu position, placed just below WooCommerce.
	 *
	 * @var int
	 */
	const POSITION = 58;

	/**
	 * Hook suffix of this plugin's admin page.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	/**
	 * Add the top level menu.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		$hook = add_menu_page(
			Screen::page_title( Screen::VIEW_DASHBOARD ),
			__( 'Catalog Health', 'catavio' ),
			Capabilities::get(),
			Screen::PAGE,
			array( $this, 'render' ),
			'dashicons-chart-bar',
			self::POSITION
		);

		if ( is_string( $hook ) ) {
			$this->hook = $hook;
		}
	}

	/**
	 * Resolve a hook suffix to the view being shown.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return string The view, or an empty string when the page is not ours.
	 */
	public function get_view_for_hook( $hook_suffix ) {
		if ( '' !== $this->hook && $hook_suffix === $this->hook ) {
			return Screen::current_view();
		}

		return '';
	}

	/**
	 * Render the requested view.
	 *
	 * @return void
	 */
	public function render() {
		Screen::render( Screen::current_view() );
	}
}
