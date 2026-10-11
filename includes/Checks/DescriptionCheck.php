<?php
/**
 * Product description check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * The long description is what the product page is built around.
 */
class DescriptionCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_description';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Description', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return Issue::SEVERITY_CRITICAL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product description is missing.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return ! $this->is_blank( $snapshot->description );
	}
}
