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
	 * One-time handoff to the first-run guide.
	 *
	 * @var string
	 */
	const ONBOARDING_OPTION = 'catavio_onboarding_pending';

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

		$is_first_install = false === get_option( self::VERSION_OPTION, false );

		update_option( self::VERSION_OPTION, CATAVIO_VERSION, false );
		if ( $is_first_install ) {
			add_option( self::ONBOARDING_OPTION, '1', '', false );
		}
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

	/**
	 * Send a newly installed site to Catavio once after activation.
	 *
	 * The option is added only on first activation, so reactivation does not
	 * interrupt an existing site's normal workflow.
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_onboarding() {
		if ( ! get_option( self::ONBOARDING_OPTION ) || ! Capabilities::current_user_can_manage() ) {
			return;
		}

		global $pagenow;
		if ( 'plugins.php' !== $pagenow || wp_doing_ajax() ) {
			return;
		}

		delete_option( self::ONBOARDING_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=catavio' ) );
		exit;
	}
}
