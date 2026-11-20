<?php
/**
 * Brand check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * A brand from WooCommerce's built-in Brands taxonomy.
 *
 * Off until turned on in Settings; see OptionalCheckInterface for why.
 *
 * @since 1.1.0
 */
class BrandCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_brand';
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
		return __( 'Brand', 'catavio' );
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

		return __( 'The product is not assigned to a brand.', 'catavio' );
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
	 * Only when the Brands taxonomy exists, and never to grouped products, whose
	 * brand is carried by the products they group.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		return $snapshot->brand_supported && ! $snapshot->is_grouped();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return array() !== $snapshot->brand_ids;
	}
}
