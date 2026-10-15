<?php
/**
 * Plugin Name:          Catavio – Catalog Health for WooCommerce
 * Plugin URI:           https://github.com/Safulislam/catavio
 * Description:          Find and fix common product information issues across your WooCommerce catalog from one simple dashboard.
 * Version:              1.0.2
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Saiful Islam
 * Author URI:           https://profiles.wordpress.org/saiful09/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          catavio
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 *
 * @package Catavio
 */

/*
 * On the "Requires Plugins: woocommerce" header above.
 *
 * WordPress 6.5 and newer enforce it: the Activate link is disabled until
 * WooCommerce is installed and active, and the dependency is stated on the
 * Plugins screen and in the installer before anything is downloaded. That is
 * the best place for this to be said, so it is said there.
 *
 * It does not make the Requirements class redundant, and Requirements is not
 * made redundant by it, because the header answers only one of four questions:
 *
 *   - It checks that WooCommerce is active, not which version. The WooCommerce
 *     8.0 floor is still enforced in PHP.
 *   - It is ignored by WordPress 6.4, the oldest release this plugin supports,
 *     where an unmet dependency still has to explain itself.
 *   - It says nothing about the PHP version.
 *   - It says nothing about the WordPress version.
 *
 * So the plugin still activates without assuming WooCommerce is there, still
 * stays inert rather than fatally erroring when it is not, and still explains
 * itself on the Plugins screen for the cases the header cannot cover.
 */

declare( strict_types = 1 );

namespace Catavio;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.2';

define( 'CATAVIO_VERSION', VERSION );
define( 'CATAVIO_PLUGIN_FILE', __FILE__ );
define( 'CATAVIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATAVIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CATAVIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CATAVIO_MIN_PHP', '7.4' );
define( 'CATAVIO_MIN_WP', '6.4' );
define( 'CATAVIO_MIN_WC', '8.0' );

/**
 * Load the Composer autoloader.
 *
 * The plugin has no production dependencies; vendor/ contains only the
 * generated autoloader. It is absent when the repository has been cloned
 * instead of the release zip installed, so fail with a notice rather than a
 * fatal error.
 *
 * @return bool True when the autoloader was loaded.
 */
function load_autoloader() {
	$autoloader = CATAVIO_PLUGIN_DIR . 'vendor/autoload.php';

	if ( ! is_readable( $autoloader ) ) {
		return false;
	}

	require_once $autoloader;

	return true;
}

if ( ! load_autoloader() ) {
	/*
	 * Confined to the Plugins screen, like every other notice this plugin can
	 * raise: that is where the problem is actionable, and no admin page outside
	 * it is interrupted. Requirements::is_notice_screen() does the same thing
	 * for the requirement notices, but cannot be reused here — without the
	 * autoloader there is no class to call.
	 */
	add_action(
		'admin_notices',
		function () {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			$screen = get_current_screen();

			if ( ! $screen instanceof \WP_Screen || 'plugins' !== $screen->id ) {
				return;
			}

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Catavio could not start because its autoloader is missing. Run "composer install" in the plugin directory, or install the packaged release.', 'catavio' )
			);
		}
	);

	return;
}

register_activation_hook( __FILE__, array( Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Lifecycle::class, 'deactivate' ) );
add_action( 'admin_init', array( Lifecycle::class, 'maybe_redirect_to_onboarding' ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 *
 * The plugin reads product data only and never touches orders, so it is
 * compatible by construction.
 *
 * @return void
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CATAVIO_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CATAVIO_PLUGIN_FILE, true );
	}
);

add_action(
	'plugins_loaded',
	function () {
		Plugin::instance()->boot();
	}
);
