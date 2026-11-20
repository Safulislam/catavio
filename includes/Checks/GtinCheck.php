<?php
/**
 * GTIN check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * A global product identifier, stored in WooCommerce's own field.
 *
 * Search engines and shopping services use it to match a product to the same
 * item sold elsewhere. Variable products are left out: the identifier belongs
 * on each variation, and checking variations one by one would multiply the cost
 * of a scan.
 *
 * Off until turned on in Settings; see OptionalCheckInterface for why.
 *
 * @since 1.1.0
 */
class GtinCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_gtin';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_group() {
		return Registry::GROUP_IDENTIFIERS;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'GTIN, UPC, EAN or ISBN', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return Issue::SEVERITY_INFO;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product has no GTIN, UPC, EAN or ISBN.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_enabled_by_default() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Only simple and external products, and only on WooCommerce versions with
	 * the built-in field (9.1 and later).
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		if ( ! $snapshot->gtin_supported ) {
			return false;
		}

		return in_array( $snapshot->type, array( 'simple', 'external' ), true );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return '' !== trim( $snapshot->global_unique_id );
	}
}
