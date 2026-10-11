<?php
/**
 * Product category check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Uncategorised products are hard to browse and filter.
 *
 * WooCommerce assigns its default category, normally "Uncategorized", to any
 * product saved without one, so a product is never literally category-less in
 * practice. Checking only for an empty list would therefore never report
 * anything. A product sitting in the default bucket is what a store owner means
 * by uncategorised, so that is what is reported.
 */
class CategoryCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_category';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Product category', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product is not assigned to a category.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return array() !== $snapshot->get_real_category_ids();
	}
}
