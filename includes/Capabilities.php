<?php
/**
 * Capability resolution.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the capability that guards this plugin.
 *
 * Every menu registration, asset enqueue and REST permission callback resolves
 * its capability here. is_admin() is never used as an authorization check.
 */
class Capabilities {

	/**
	 * Preferred capability, registered by WooCommerce.
	 *
	 * @var string
	 */
	const PRIMARY = 'manage_woocommerce';

	/**
	 * Fallback used when WooCommerce has not registered its capabilities.
	 *
	 * @var string
	 */
	const FALLBACK = 'edit_products';

	/**
	 * Last resort, always present in WordPress.
	 *
	 * @var string
	 */
	const CORE_FALLBACK = 'manage_options';

	/**
	 * Memoized resolved capability.
	 *
	 * @var string
	 */
	private static $resolved = '';

	/**
	 * Get the capability required to use this plugin.
	 *
	 * @return string
	 */
	public static function get() {
		if ( '' === self::$resolved ) {
			self::$resolved = self::resolve();
		}

		/**
		 * Filters the capability required to use Catavio.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capability The resolved capability.
		 */
		return (string) apply_filters( 'catavio_capability', self::$resolved );
	}

	/**
	 * Determine whether the current user may use this plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::get() );
	}

	/**
	 * Reset the memoized capability.
	 *
	 * Used by the test suite when roles change mid-run.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$resolved = '';
	}

	/**
	 * Pick the first capability that any role actually holds.
	 *
	 * @return string
	 */
	private static function resolve() {
		$candidates = array( self::PRIMARY, self::FALLBACK );

		foreach ( $candidates as $candidate ) {
			if ( self::is_registered( $candidate ) ) {
				return $candidate;
			}
		}

		return self::CORE_FALLBACK;
	}

	/**
	 * Determine whether a capability is granted to at least one role.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	private static function is_registered( $capability ) {
		$roles = wp_roles();

		foreach ( $roles->role_objects as $role ) {
			if ( $role->has_cap( $capability ) ) {
				return true;
			}
		}

		return false;
	}
}
