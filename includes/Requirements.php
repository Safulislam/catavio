<?php
/**
 * Environment and dependency checks.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the plugin can run, and explains it when it cannot.
 *
 * Nothing in this class may touch WooCommerce APIs: it runs before the
 * WooCommerce availability question has been answered.
 */
class Requirements {

	/**
	 * Check the PHP and WordPress environment.
	 *
	 * Returns unescaped text. Escaping happens once, where the message is
	 * actually output, because escaping here as well would double encode any
	 * translation containing an ampersand or a quote.
	 *
	 * @return string Empty string when the environment is supported, otherwise
	 *                a translated explanation.
	 */
	public static function check_environment() {
		if ( version_compare( PHP_VERSION, CATAVIO_MIN_PHP, '<' ) ) {
			return sprintf(
				/* translators: 1: required PHP version, 2: PHP version in use. */
				__( 'Catavio requires PHP %1$s or newer. This site runs PHP %2$s.', 'catavio' ),
				CATAVIO_MIN_PHP,
				PHP_VERSION
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), CATAVIO_MIN_WP, '<' ) ) {
			return sprintf(
				/* translators: 1: required WordPress version, 2: WordPress version in use. */
				__( 'Catavio requires WordPress %1$s or newer. This site runs WordPress %2$s.', 'catavio' ),
				CATAVIO_MIN_WP,
				get_bloginfo( 'version' )
			);
		}

		return '';
	}

	/**
	 * Determine whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Determine whether the active WooCommerce version is supported.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_supported() {
		if ( ! self::is_woocommerce_active() ) {
			return false;
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			return false;
		}

		return version_compare( WC_VERSION, CATAVIO_MIN_WC, '>=' );
	}

	/**
	 * Determine whether every requirement is satisfied.
	 *
	 * @return bool
	 */
	public static function are_met() {
		return '' === self::check_environment() && self::is_woocommerce_supported();
	}

	/**
	 * Register the admin notice explaining an unmet requirement.
	 *
	 * @return void
	 */
	public static function register_notices() {
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	/**
	 * Render the admin notice for the first unmet requirement.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! self::is_notice_screen() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = self::check_environment();

		if ( '' === $message ) {
			$message = self::dependency_message();
		}

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Determine whether the current admin screen may show the notice.
	 *
	 * The notice appears on the Plugins screen and nowhere else. That is the
	 * screen the user is returned to after activating, so it is where the
	 * message is both seen and actionable, and confining it there keeps the
	 * plugin off every other admin page — the Dashboard included.
	 *
	 * get_current_screen() is unavailable on a handful of early admin requests,
	 * which are treated as "not our screen" rather than guessed at.
	 *
	 * @return bool
	 */
	private static function is_notice_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen instanceof \WP_Screen && 'plugins' === $screen->id;
	}

	/**
	 * Build the WooCommerce dependency message.
	 *
	 * Returns unescaped text; see check_environment().
	 *
	 * @return string Empty string when WooCommerce is present and supported.
	 */
	private static function dependency_message() {
		if ( ! self::is_woocommerce_active() ) {
			return __( 'Catavio requires WooCommerce to be active.', 'catavio' );
		}

		if ( ! self::is_woocommerce_supported() ) {
			$version = defined( 'WC_VERSION' ) ? WC_VERSION : __( 'unknown', 'catavio' );

			return sprintf(
				/* translators: 1: required WooCommerce version, 2: WooCommerce version in use. */
				__( 'Catavio requires WooCommerce %1$s or newer. This site runs WooCommerce %2$s.', 'catavio' ),
				CATAVIO_MIN_WC,
				$version
			);
		}

		return '';
	}
}
