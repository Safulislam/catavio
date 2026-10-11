<?php
/**
 * Contract for a single product check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * One deterministic rule about product data.
 *
 * Implementations must be pure: given the same snapshot they must always return
 * the same answer. No randomness, no clock, no network, no database.
 */
interface CheckInterface {

	/**
	 * Stable identifier, for example "missing_sku".
	 *
	 * Stored in post meta and used as a filter value, so it must never change
	 * once released.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Short translated label naming the check.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Severity reported when the check fails.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string One of the Issue::SEVERITY_* constants.
	 */
	public function get_severity( ProductSnapshot $snapshot );

	/**
	 * Translated explanation of the failure.
	 *
	 * Must be a complete static sentence. Runtime values are never interpolated
	 * into translated strings.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot );

	/**
	 * Whether this check is meaningful for this product.
	 *
	 * Checks that do not apply are excluded from the result entirely: they are
	 * neither failed nor passed, and they do not affect the score.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot );

	/**
	 * Whether the product satisfies this check.
	 *
	 * Only called when applies_to() returned true.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot );
}
