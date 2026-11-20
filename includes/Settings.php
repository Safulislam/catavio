<?php
/**
 * Store owner preferences.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

defined( 'ABSPATH' ) || exit;

/**
 * Which checks a store has chosen to turn off.
 *
 * Not every store uses every field WooCommerce offers. A shop that never
 * assigns SKUs would otherwise see every product flagged for something it has
 * decided not to do, and the real gaps would be lost among them. Turning a
 * check off removes it from future scans; it never edits a product.
 *
 * Only differences from each check's default are stored: default checks that
 * are turned off, and optional checks (see OptionalCheckInterface) that are
 * turned on. So a check added in a later version, or by an extension through
 * the catavio_checks filter, starts in its own default state rather than being
 * silently excluded or silently included.
 */
class Settings {

	/**
	 * Option name. Deliberately not autoloaded: only this plugin's own screens
	 * and scans read it.
	 *
	 * @var string
	 */
	const OPTION = 'catavio_settings';

	/**
	 * Read the turned-off check identifiers.
	 *
	 * @param string[] $default_off Checks that are off until turned on. Each is
	 *                              reported as off unless the store owner has
	 *                              turned it on.
	 * @return string[]
	 */
	public function get_disabled_checks( array $default_off = array() ) {
		$stored   = $this->get();
		$disabled = $stored['disabled_checks'];

		foreach ( $default_off as $check_id ) {
			$check_id = (string) $check_id;

			if ( ! in_array( $check_id, $stored['enabled_checks'], true ) && ! in_array( $check_id, $disabled, true ) ) {
				$disabled[] = $check_id;
			}
		}

		return $disabled;
	}

	/**
	 * Determine whether a check is turned on.
	 *
	 * @param string   $check_id    Check identifier.
	 * @param string[] $default_off Checks that are off until turned on.
	 * @return bool
	 */
	public function is_enabled( $check_id, array $default_off = array() ) {
		return ! in_array( (string) $check_id, $this->get_disabled_checks( $default_off ), true );
	}

	/**
	 * When the settings were last changed.
	 *
	 * Compared against the last completed scan, so the dashboard can say when
	 * its figures were produced under different settings.
	 *
	 * @return int Unix timestamp, or zero when never changed.
	 */
	public function get_updated_at() {
		$stored = $this->get();

		return $stored['updated_at'];
	}

	/**
	 * Store the turned-off checks.
	 *
	 * Each choice is stored as the difference from the check's default: a
	 * default check that is turned off, or an optional check that is turned on.
	 * Identifiers that are not in the known list are dropped, so nothing the
	 * server did not define is ever stored. The timestamp only moves when the
	 * outcome actually changes, so saving the same choice twice does not tell
	 * the dashboard its figures are out of date.
	 *
	 * @param string[] $disabled    Check identifiers to turn off.
	 * @param string[] $known       Every registered check identifier.
	 * @param string[] $default_off Checks that are off until turned on.
	 * @return string[] Every turned-off identifier, as get_disabled_checks() reports it.
	 */
	public function set_disabled_checks( array $disabled, array $known, array $default_off = array() ) {
		$known       = array_map( 'strval', $known );
		$default_off = array_map( 'strval', $default_off );
		$disabled    = array_map( 'strval', $disabled );

		$off = array();
		$on  = array();

		foreach ( $known as $check_id ) {
			$wants_off = in_array( $check_id, $disabled, true );

			if ( in_array( $check_id, $default_off, true ) ) {
				if ( ! $wants_off ) {
					$on[] = $check_id;
				}
			} elseif ( $wants_off ) {
				$off[] = $check_id;
			}
		}

		$before = $this->get_disabled_checks( $default_off );
		$after  = array_values( array_merge( $off, array_diff( $default_off, $on ) ) );

		sort( $before );
		sort( $after );

		if ( $before === $after ) {
			return $this->get_disabled_checks( $default_off );
		}

		update_option(
			self::OPTION,
			array(
				'disabled_checks' => $off,
				'enabled_checks'  => $on,
				'updated_at'      => time(),
			),
			false
		);

		/**
		 * Fires after the store owner changes which checks run.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $disabled Every turned-off check identifier.
		 */
		do_action( 'catavio_settings_saved', $this->get_disabled_checks( $default_off ) );

		return $this->get_disabled_checks( $default_off );
	}

	/**
	 * Read and normalise the stored option.
	 *
	 * @return array{disabled_checks: string[], enabled_checks: string[], updated_at: int}
	 */
	private function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'disabled_checks' => self::read_ids( $stored, 'disabled_checks' ),
			'enabled_checks'  => self::read_ids( $stored, 'enabled_checks' ),
			'updated_at'      => isset( $stored['updated_at'] ) ? max( 0, (int) $stored['updated_at'] ) : 0,
		);
	}

	/**
	 * Read a list of check identifiers from the stored option.
	 *
	 * @param array<string, mixed> $stored Stored option.
	 * @param string               $key    List key.
	 * @return string[]
	 */
	private static function read_ids( array $stored, $key ) {
		$ids = array();

		if ( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ) {
			foreach ( $stored[ $key ] as $check_id ) {
				if ( is_string( $check_id ) && '' !== $check_id ) {
					$ids[] = $check_id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
