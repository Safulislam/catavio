<?php
/**
 * Admin screen definitions and markup.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Admin;

use Catavio\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the plugin's admin views and renders their container markup.
 *
 * One admin page, addressed by one slug, showing one of two views chosen by a
 * "tab" query argument. A WordPress top level menu repeats itself as its own
 * first submenu item as soon as it has any submenu at all, which is how this
 * plugin ended up offering "Catalog Health" and "Dashboard" as two routes to
 * the same screen. Registering no submenus removes the repetition at its
 * source: one sidebar entry, and the tabs on the page say what the views are.
 *
 * The product detail view is not a third view either: it is a view within the
 * product list, addressed by a "product" query argument on the same tab.
 */
class Screen {

	/**
	 * The plugin's one admin page slug, and its top level menu slug.
	 *
	 * @var string
	 */
	const PAGE = 'catavio';

	/**
	 * Query argument naming the view.
	 *
	 * @var string
	 */
	const TAB = 'tab';

	/**
	 * Dashboard view.
	 *
	 * @var string
	 */
	const VIEW_DASHBOARD = 'dashboard';

	/**
	 * Product health list view.
	 *
	 * @var string
	 */
	const VIEW_PRODUCTS = 'products';

	/**
	 * DOM id of the React mount point.
	 *
	 * @var string
	 */
	const ROOT_ID = 'catavio-admin-root';

	/**
	 * Get every view, in the order the tabs present them.
	 *
	 * @return string[]
	 */
	public static function views() {
		return array( self::VIEW_DASHBOARD, self::VIEW_PRODUCTS );
	}

	/**
	 * Resolve the view being requested.
	 *
	 * Anything unrecognised falls back to the dashboard, so a hand-edited or
	 * stale URL lands somewhere real instead of on an empty screen.
	 *
	 * @return string One of the VIEW_* constants.
	 */
	public static function current_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses which read-only view to render; it changes nothing.
		$requested = isset( $_GET[ self::TAB ] ) ? sanitize_key( wp_unslash( $_GET[ self::TAB ] ) ) : '';

		if ( in_array( $requested, self::views(), true ) ) {
			return $requested;
		}

		return self::VIEW_DASHBOARD;
	}

	/**
	 * Get the page title for a screen.
	 *
	 * This is the browser title, so it names the view rather than the section:
	 * two open tabs have to be tellable apart from the title alone.
	 *
	 * Must be called on or after admin_menu so translations are loaded.
	 *
	 * @param string $view One of the VIEW_* constants.
	 * @return string
	 */
	public static function page_title( $view ) {
		if ( self::VIEW_PRODUCTS === $view ) {
			return __( 'Product Health', 'catavio' );
		}

		return __( 'Catalog Health', 'catavio' );
	}

	/**
	 * Get the heading shown on every screen.
	 *
	 * One heading for the whole section, not one per screen. The tabs beneath
	 * it name the view, so the page never presents a third name for something
	 * the sidebar has already called something else.
	 *
	 * @return string
	 */
	public static function heading() {
		return __( 'Catalog Health', 'catavio' );
	}

	/**
	 * Get the admin URL of a view.
	 *
	 * The dashboard is the default view, so it carries no tab argument: the
	 * bare page URL is the shortest thing that works and the thing the menu
	 * itself links to.
	 *
	 * @param string $view One of the VIEW_* constants.
	 * @return string
	 */
	public static function url( $view ) {
		$args = array( 'page' => self::PAGE );

		if ( self::VIEW_DASHBOARD !== $view ) {
			$args[ self::TAB ] = $view;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Get the menu label for a screen.
	 *
	 * @param string $view One of the VIEW_* constants.
	 * @return string
	 */
	public static function menu_title( $view ) {
		if ( self::VIEW_PRODUCTS === $view ) {
			return __( 'Product Health', 'catavio' );
		}

		return __( 'Dashboard', 'catavio' );
	}

	/**
	 * Render a screen's container markup.
	 *
	 * The heading is rendered server side so the page is titled and announced
	 * correctly even before the script runs, or if it fails to run at all.
	 * Everything below the heading is owned by React.
	 *
	 * @param string $view One of the VIEW_* constants.
	 * @return void
	 */
	public static function render( $view ) {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'catavio' ), '', array( 'response' => 403 ) );
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( self::heading() ); ?></h1>
			<?php // Where WordPress relocates admin notices, so they land under the heading rather than above it. ?>
			<hr class="wp-header-end">
			<?php self::render_tabs( $view ); ?>
			<div id="<?php echo esc_attr( self::ROOT_ID ); ?>" class="catavio-admin-root">
				<noscript>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Catavio needs JavaScript to display product health results.', 'catavio' ); ?></p>
					</div>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the tab bar linking the screens to each other.
	 *
	 * Plain links, not an ARIA tab widget: each tab is a separate admin page
	 * rather than a panel swapped in place, and calling them tabs to assistive
	 * technology would promise keyboard behaviour that a page load does not
	 * have.
	 *
	 * The classes are the plugin's own rather than core's nav-tab set. Core's
	 * tabs carry a fixed look built from a dozen declarations — floats, bevels,
	 * a full width rule — and restyling them means overriding each one and
	 * re-overriding it whenever core adjusts them. Owning the class owns the
	 * design.
	 *
	 * aria-current is always present. The value "false" is the specified way to
	 * say "not the current item", so the attribute never has to be conditionally
	 * assembled in the markup.
	 *
	 * @param string $current The view being rendered.
	 * @return void
	 */
	private static function render_tabs( $current ) {
		?>
		<nav class="catavio-tabs" aria-label="<?php esc_attr_e( 'Catalog Health sections', 'catavio' ); ?>">
			<?php
			foreach ( self::views() as $view ) {
				$is_current = ( $view === $current );

				printf(
					'<a href="%1$s" class="%2$s" aria-current="%3$s">%4$s</a>',
					esc_url( self::url( $view ) ),
					esc_attr( $is_current ? 'catavio-tabs__tab catavio-tabs__tab--current' : 'catavio-tabs__tab' ),
					esc_attr( $is_current ? 'page' : 'false' ),
					esc_html( self::menu_title( $view ) )
				);
			}
			?>
		</nav>
		<?php
	}
}
