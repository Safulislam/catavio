<?php
/**
 * Shared behaviour for checks.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Base class providing the defaults most checks want.
 */
abstract class AbstractCheck implements CheckInterface {

	/**
	 * Severity reported when the check fails.
	 *
	 * Overridden by checks whose severity depends on the product type.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return Issue::SEVERITY_WARNING;
	}

	/**
	 * Applies to every product type by default.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return true;
	}

	/**
	 * Determine whether a string is empty once stripped of markup and space.
	 *
	 * Used so that a description containing only an empty paragraph or a
	 * non breaking space counts as missing, which is what a store owner means.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	protected function is_blank( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return true;
		}

		$value = str_replace( array( "\xc2\xa0", '&nbsp;' ), ' ', $value );
		$value = wp_strip_all_tags( $value );

		return '' === trim( $value );
	}
}
