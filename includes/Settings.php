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
 * Only the turned-off list is stored, never the turned-on one, so a check added
 * in a later version, or by an extension through the catavio_checks filter, is
 * on by default rather than silently excluded.
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
	 * @return string[]
	 */
	public function get_disabled_checks() {
		$stored = $this->get();

		return $stored['disabled_checks'];
	}

	/**
	 * Determine whether a check is turned on.
	 *
	 * @param string $check_id Check identifier.
	 * @return bool
	 */
	public function is_enabled( $check_id ) {
		return ! in_array( (string) $check_id, $this->get_disabled_checks(), true );
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
	 * Identifiers that are not in the known list are dropped, so nothing the
	 * server did not define is ever stored. The timestamp only moves when the
	 * list actually changes, so saving the same choice twice does not tell the
	 * dashboard its figures are out of date.
	 *
	 * @param string[] $disabled Check identifiers to turn off.
	 * @param string[] $known    Every registered check identifier.
	 * @return string[] The turned-off identifiers as stored.
	 */
	public function set_disabled_checks( array $disabled, array $known ) {
		$known = array_map( 'strval', $known );
		$clean = array();

		foreach ( $disabled as $check_id ) {
			$check_id = (string) $check_id;

			if ( in_array( $check_id, $known, true ) && ! in_array( $check_id, $clean, true ) ) {
				$clean[] = $check_id;
			}
		}

		$current = $this->get_disabled_checks();

		$before = $current;
		$after  = $clean;

		sort( $before );
		sort( $after );

		if ( $before === $after ) {
			return $current;
		}

		update_option(
			self::OPTION,
			array(
				'disabled_checks' => $clean,
				'updated_at'      => time(),
			),
			false
		);

		return $clean;
	}

	/**
	 * Read and normalise the stored option.
	 *
	 * @return array{disabled_checks: string[], updated_at: int}
	 */
	private function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$disabled = array();

		if ( isset( $stored['disabled_checks'] ) && is_array( $stored['disabled_checks'] ) ) {
			foreach ( $stored['disabled_checks'] as $check_id ) {
				if ( is_string( $check_id ) && '' !== $check_id ) {
					$disabled[] = $check_id;
				}
			}
		}

		return array(
			'disabled_checks' => array_values( array_unique( $disabled ) ),
			'updated_at'      => isset( $stored['updated_at'] ) ? max( 0, (int) $stored['updated_at'] ) : 0,
		);
	}
}
