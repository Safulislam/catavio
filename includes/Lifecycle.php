<?php
/**
 * Plugin activation and deactivation handling.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the plugin activation and deactivation hooks.
 */
class Lifecycle {

	/**
	 * Option holding the installed plugin version, used by upgrade routines.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'catavio_version';

	/**
	 * Run on plugin activation.
	 *
	 * Activation deliberately does not require WooCommerce. The plugin stays
	 * inert and shows an admin notice instead, so activating it in any order
	 * relative to WooCommerce is safe.
	 *
	 * @return void
	 */
	public static function activate() {
		$environment = Requirements::check_environment();

		if ( '' !== $environment ) {
			deactivate_plugins( CATAVIO_PLUGIN_BASENAME );
			wp_die(
				esc_html( $environment ),
				esc_html__( 'Plugin activation failed', 'catavio' ),
				array( 'back_link' => true )
			);
		}

		update_option( self::VERSION_OPTION, CATAVIO_VERSION, false );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Deactivation clears transient runtime state only. Stored scan results and
	 * the version option survive, so reactivating does not force a rescan;
	 * uninstall.php removes everything.
	 *
	 * @return void
	 */
	public static function deactivate() {
		delete_option( 'catavio_scan_state' );
	}
}
